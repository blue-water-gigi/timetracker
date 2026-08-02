<?php

declare(strict_types=1);

use App\Contracts\Queries\GetProjectList;
use App\Enums\ProjectRole;
use App\Events\ProjectListChanged;
use App\Models\User;
use App\Models\Workspace;
use App\Providers\AppServiceProvider;
use App\Services\Project\Actions\CreateProject;
use App\Services\Project\Actions\DeleteProject;
use App\Services\Project\Actions\UpdateProject;
use App\Services\ProjectMember\Actions\CreateProjectMember;
use App\Services\ProjectMember\Actions\DeleteProjectMember;
use App\Services\ProjectMember\Actions\UpdateProjectMember;
use App\Services\Workspace\Actions\UpdateWorkspace;
use App\Support\Cache\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TenantFixture;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate:fresh');

    config()->set('redis_features.workspace_summary.enabled', false);
    config()->set('redis_features.project_list.enabled', true);
    config()->set('redis_features.project_list.store', 'redis');
    config()->set('redis_features.project_list.ttl', 120);

    (new AppServiceProvider($this->app))->register();
});

it('invalidates a cached list throughout the project lifecycle', function (): void {
    $tenant = TenantFixture::create();
    flushRedisProjectList($tenant->workspace, $tenant->admin);

    try {
        $initial = redisProjectList($tenant->workspace, $tenant->admin);

        $project = app(CreateProject::class)->handle(
            $tenant->workspace,
            ['name' => 'Redis project list', 'slug' => 'redis-project-list'],
            (int) $tenant->admin->getKey(),
        );
        $afterCreate = redisProjectList($tenant->workspace, $tenant->admin);

        app(UpdateProject::class)->handle(
            $project,
            ['name' => 'Renamed Redis project'],
            (int) $tenant->admin->getKey(),
        );
        $afterUpdate = redisProjectList($tenant->workspace, $tenant->admin);

        app(DeleteProject::class)->handle($project);
        $afterDelete = redisProjectList($tenant->workspace, $tenant->admin);

        expect($initial['meta']['total'])->toBe(1)
            ->and($afterCreate['meta']['total'])->toBe(2)
            ->and(data_get(
                collect($afterUpdate['data'])->firstWhere('id', $project->getKey()),
                'name',
            ))->toBe('Renamed Redis project')
            ->and($afterDelete['meta']['total'])->toBe(1);
    } finally {
        flushRedisProjectList($tenant->workspace, $tenant->admin);
    }
})->group('redis');

it('invalidates membership counts after adding and deleting a member', function (): void {
    $tenant = TenantFixture::create();
    $employee = $tenant->employee();
    flushRedisProjectList($tenant->workspace, $tenant->admin);

    try {
        $initial = redisProjectList($tenant->workspace, $tenant->admin);

        $membership = app(CreateProjectMember::class)->handle(
            $tenant->project,
            [
                'user_id' => (int) $employee->getKey(),
                'project_role' => ProjectRole::PARTICIPANT->value,
                'active' => true,
            ],
        );
        $afterCreate = redisProjectList($tenant->workspace, $tenant->admin);

        app(DeleteProjectMember::class)->handle($membership);
        $afterDelete = redisProjectList($tenant->workspace, $tenant->admin);

        expect(data_get($initial, 'data.0.membershipsCount'))->toBe(0)
            ->and(data_get($afterCreate, 'data.0.membershipsCount'))->toBe(1)
            ->and(data_get($afterDelete, 'data.0.membershipsCount'))->toBe(0);
    } finally {
        flushRedisProjectList($tenant->workspace, $tenant->admin);
    }
})->group('redis');

it('invalidates employee visibility after membership is deactivated', function (): void {
    $tenant = TenantFixture::create();
    $employee = $tenant->employee();
    $membership = $tenant->membership($employee, role: ProjectRole::PARTICIPANT);
    flushRedisProjectList($tenant->workspace, $employee);

    try {
        $visible = redisProjectList($tenant->workspace, $employee);

        app(UpdateProjectMember::class)->handle(
            $membership,
            ['active' => false],
        );

        $hidden = redisProjectList($tenant->workspace, $employee);

        expect($visible['data'])->toHaveCount(1)
            ->and($hidden['data'])->toBeEmpty();
    } finally {
        flushRedisProjectList($tenant->workspace, $employee);
    }
})->group('redis');

it('invalidates project list workspace data after updating a workspace', function (): void {
    $tenant = TenantFixture::create();
    flushRedisProjectList($tenant->workspace, $tenant->admin);

    try {
        $initial = redisProjectList($tenant->workspace, $tenant->admin);

        app(UpdateWorkspace::class)->handle(
            $tenant->workspace,
            ['name' => 'Renamed workspace'],
        );

        $refreshed = redisProjectList($tenant->workspace, $tenant->admin);

        expect(data_get($initial, 'data.0.workspace.name'))->not->toBe('Renamed workspace')
            ->and(data_get($refreshed, 'data.0.workspace.name'))->toBe('Renamed workspace');
    } finally {
        flushRedisProjectList($tenant->workspace, $tenant->admin);
    }
})->group('redis');

it('does not invalidate another workspace project list', function (): void {
    $tenantA = TenantFixture::create();
    $tenantB = TenantFixture::create();
    flushRedisProjectList($tenantA->workspace, $tenantA->admin);
    flushRedisProjectList($tenantB->workspace, $tenantB->admin);

    try {
        redisProjectList($tenantA->workspace, $tenantA->admin);
        $cachedB = redisProjectList($tenantB->workspace, $tenantB->admin);

        app(CreateProject::class)->handle(
            $tenantA->workspace,
            ['name' => 'Tenant A project', 'slug' => 'tenant-a-project'],
            (int) $tenantA->admin->getKey(),
        );

        $refreshedA = redisProjectList($tenantA->workspace, $tenantA->admin);
        $stillCachedB = redisProjectList($tenantB->workspace, $tenantB->admin);

        expect($refreshedA['meta']['total'])->toBe(2)
            ->and($stillCachedB)->toBe($cachedB);
    } finally {
        flushRedisProjectList($tenantA->workspace, $tenantA->admin);
        flushRedisProjectList($tenantB->workspace, $tenantB->admin);
    }
})->group('redis');

it('does not dispatch a project list event after transaction rollback', function (): void {
    Event::fake([ProjectListChanged::class]);

    try {
        DB::transaction(function (): never {
            ProjectListChanged::dispatch(17, 'rollback_probe');

            throw new RuntimeException('Rollback.');
        });
    } catch (RuntimeException) {
        // The rollback is the behavior under test.
    }

    Event::assertNotDispatched(ProjectListChanged::class);
})->group('redis');

function redisProjectList(Workspace $workspace, User $viewer, int $page = 1, int $perPage = 15): array
{
    return app(GetProjectList::class)->execute($workspace, $viewer, $page, $perPage);
}

function flushRedisProjectList(Workspace $workspace, User $viewer): void
{
    $cache = Cache::store('redis');
    $workspaceId = (int) $workspace->getKey();

    $cache->tags([CacheKeys::projectTag($workspaceId)])->flush();

    foreach ([[1, 15], [1, 10], [2, 10], [2, 15]] as [$page, $perPage]) {
        $cache->forget(CacheKeys::projectList(
            $workspaceId,
            (int) $viewer->getKey(),
            $page,
            $perPage,
        ));
    }
}
