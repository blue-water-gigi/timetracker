<?php

declare(strict_types=1);

use App\Contracts\Cache\WorkspaceCacheInvalidator;
use App\Contracts\Queries\GetWorkspaceSummary;
use App\Enums\ProjectRole;
use App\Events\WorkspaceReadModelChanged;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Providers\AppServiceProvider;
use App\Services\Project\Actions\CreateProject;
use App\Services\Project\Actions\DeleteProject;
use App\Services\ProjectMember\Actions\CreateProjectMember;
use App\Services\ProjectMember\Actions\DeleteProjectMember;
use App\Services\Timesheet\Data\TimesheetPeriodData;
use App\Services\Timesheet\TimesheetService;
use App\Support\Cache\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TenantFixture;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate:fresh');

    config()->set('redis_features.workspace_summary.enabled', true);
    config()->set('redis_features.workspace_summary.store', 'redis');
    config()->set('redis_features.workspace_summary.ttl', 120);

    (new AppServiceProvider($this->app))->register();
});

it('serves a cached summary until its workspace tag is invalidated', function (): void {
    $tenant = TenantFixture::create();
    flushRedisWorkspaceSummary($tenant->workspace);

    try {
        $first = redisWorkspaceSummary($tenant->workspace, $tenant->admin);

        Project::factory()->for($tenant->workspace)->create();

        $cached = redisWorkspaceSummary($tenant->workspace, $tenant->admin);

        app(WorkspaceCacheInvalidator::class)->invalidate((int) $tenant->workspace->getKey());

        $refreshed = redisWorkspaceSummary($tenant->workspace, $tenant->admin);

        expect($first['projects_count'])->toBe(1)
            ->and($cached['projects_count'])->toBe(1)
            ->and($refreshed['projects_count'])->toBe(2);
    } finally {
        flushRedisWorkspaceSummary($tenant->workspace);
    }
})->group('redis');

it('invalidates summary counts after project membership and timesheet actions', function (): void {
    $tenant = TenantFixture::create();
    $employee = $tenant->employee();
    flushRedisWorkspaceSummary($tenant->workspace);

    try {
        $initial = redisWorkspaceSummary($tenant->workspace, $employee);

        $createdProject = app(CreateProject::class)->handle(
            $tenant->workspace,
            ['name' => 'Redis integration project', 'slug' => 'redis-integration-project'],
            (int) $tenant->admin->getKey(),
        );
        $afterProjectCreate = redisWorkspaceSummary($tenant->workspace, $employee);

        $membership = app(CreateProjectMember::class)->handle(
            $tenant->project,
            [
                'user_id' => (int) $employee->getKey(),
                'project_role' => ProjectRole::PARTICIPANT->value,
                'active' => true,
            ],
        );
        $afterMembershipCreate = redisWorkspaceSummary($tenant->workspace, $employee);

        $periodStart = today()->startOfWeek();
        $timesheetService = app(TimesheetService::class);
        $timesheet = $timesheetService->create(
            $tenant->project,
            $employee,
            TimesheetPeriodData::fromValidated([
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodStart->copy()->endOfWeek()->toDateString(),
            ]),
        );
        $afterTimesheetCreate = redisWorkspaceSummary($tenant->workspace, $employee);

        $submitted = $timesheetService->submit($timesheet);
        $afterSubmit = redisWorkspaceSummary($tenant->workspace, $employee);

        $timesheetService->approve($tenant->admin, $submitted, null);
        $afterReview = redisWorkspaceSummary($tenant->workspace, $employee);

        app(DeleteProjectMember::class)->handle($membership);
        $afterMembershipDelete = redisWorkspaceSummary($tenant->workspace, $employee);

        app(DeleteProject::class)->handle($createdProject);
        $afterProjectDelete = redisWorkspaceSummary($tenant->workspace, $employee);

        expect($initial)->toMatchArray([
            'projects_count' => 1,
            'members_count' => 0,
            'valid_timesheets_count' => 0,
        ])->and($afterProjectCreate['projects_count'])->toBe(2)
            ->and($afterMembershipCreate['members_count'])->toBe(1)
            ->and($afterTimesheetCreate['valid_timesheets_count'])->toBe(1)
            ->and($afterSubmit['valid_timesheets_count'])->toBe(1)
            ->and($afterReview['valid_timesheets_count'])->toBe(0)
            ->and($afterMembershipDelete['members_count'])->toBe(0)
            ->and($afterProjectDelete['projects_count'])->toBe(1);
    } finally {
        flushRedisWorkspaceSummary($tenant->workspace);
    }
})->group('redis');

it('invalidates every viewer variant without touching another workspace', function (): void {
    $tenantA = TenantFixture::create();
    $tenantB = TenantFixture::create();
    $employeeA = $tenantA->employee();

    flushRedisWorkspaceSummary($tenantA->workspace);
    flushRedisWorkspaceSummary($tenantB->workspace);

    try {
        redisWorkspaceSummary($tenantA->workspace, $tenantA->admin);
        redisWorkspaceSummary($tenantA->workspace, $employeeA);
        redisWorkspaceSummary($tenantB->workspace, $tenantB->admin);

        Project::factory()->for($tenantB->workspace)->create();

        app(CreateProject::class)->handle(
            $tenantA->workspace,
            ['name' => 'Tenant A new project', 'slug' => 'tenant-a-new-project'],
            (int) $tenantA->admin->getKey(),
        );

        $adminA = redisWorkspaceSummary($tenantA->workspace, $tenantA->admin);
        $employeeSummaryA = redisWorkspaceSummary($tenantA->workspace, $employeeA);
        $cachedB = redisWorkspaceSummary($tenantB->workspace, $tenantB->admin);

        expect($adminA['projects_count'])->toBe(2)
            ->and($employeeSummaryA['projects_count'])->toBe(2)
            ->and($tenantB->workspace->projects()->count())->toBe(2)
            ->and($cachedB['projects_count'])->toBe(1);
    } finally {
        flushRedisWorkspaceSummary($tenantA->workspace);
        flushRedisWorkspaceSummary($tenantB->workspace);
    }
})->group('redis');

it('does not publish the workspace event after a transaction rollback', function (): void {
    Event::fake([WorkspaceReadModelChanged::class]);

    try {
        DB::transaction(function (): never {
            WorkspaceReadModelChanged::dispatch(17, 'rollback_probe');

            throw new RuntimeException('Rollback.');
        });
    } catch (RuntimeException) {
        // The rollback is the behavior under test.
    }

    Event::assertNotDispatched(WorkspaceReadModelChanged::class);
})->group('redis');

function redisWorkspaceSummary(Workspace $workspace, User $viewer): array
{
    return app(GetWorkspaceSummary::class)->execute($workspace, $viewer);
}

function flushRedisWorkspaceSummary(Workspace $workspace): void
{
    Cache::store('redis')
        ->tags([CacheKeys::workspaceTag((int) $workspace->getKey())])
        ->flush();
}
