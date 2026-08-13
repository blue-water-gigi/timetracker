<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use Database\Factories\TimesheetFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;

function createProjectTeamStatisticsTimesheet(
    TenantFixture $tenant,
    Project $project,
    User $user,
    TimesheetStatus $status,
    string $periodStart,
    string $periodEnd,
): Timesheet {
    if (! ProjectMember::query()->whereBelongsTo($project)->whereBelongsTo($user)->exists()) {
        $tenant->membership($user, project: $project);
    }

    /** @var TimesheetFactory $factory */
    $factory = Timesheet::factory()->for($project)->for($user);
    $factory = match ($status) {
        TimesheetStatus::DRAFT     => $factory,
        TimesheetStatus::SUBMITTED => $factory->submitted(),
        TimesheetStatus::APPROVED  => $factory->approved($tenant->admin),
        TimesheetStatus::REJECTED  => $factory->rejected($tenant->admin),
    };

    return $factory->create([
        'period_start' => $periodStart,
        'period_end'   => $periodEnd,
    ]);
}

function projectTeamStatisticsUrl(TenantFixture $tenant, ?Project $project = null): string
{
    $project ??= $tenant->project;

    return "/api/v1/workspaces/{$tenant->workspace->id}/projects/{$project->id}/statistics/team";
}

it('requires authentication', function () {
    $tenant = TenantFixture::create();

    $this->actingAsGuest('web')
        ->getJson(projectTeamStatisticsUrl($tenant))
        ->assertUnauthorized();
});

it('allows the owner and active management members including senior', function () {
    $tenant = TenantFixture::create();
    $url    = projectTeamStatisticsUrl($tenant).'?from=2026-08-01&to=2026-08-31';

    $this->actingAs($tenant->admin)->getJson($url)->assertOk();

    foreach ([ProjectRole::MANAGER, ProjectRole::PROJECT_LEAD, ProjectRole::SENIOR] as $role) {
        $member = $tenant->employee();
        $tenant->membership($member, $role);

        $this->actingAs($member)->getJson($url)->assertOk();
    }
});

it('forbids participants, non-members and inactive management members', function () {
    $tenant          = TenantFixture::create();
    $participant     = $tenant->employee();
    $nonMember       = $tenant->employee();
    $inactiveManager = $tenant->employee();
    $tenant->membership($participant, ProjectRole::PARTICIPANT);
    $tenant->membership($inactiveManager, ProjectRole::MANAGER, active: false);

    $url = projectTeamStatisticsUrl($tenant);

    $this->actingAs($participant)->getJson($url)->assertForbidden();
    $this->actingAs($nonMember)->getJson($url)->assertForbidden();
    $this->actingAs($inactiveManager)->getJson($url)->assertForbidden();
});

it('hides foreign tenants and mismatched workspace project pairs', function () {
    $tenant          = TenantFixture::create();
    $foreign         = TenantFixture::create();
    $foreignEmployee = $foreign->employee();

    $this->actingAs($foreign->admin)
        ->getJson(projectTeamStatisticsUrl($tenant))
        ->assertNotFound();

    $this->actingAs($foreignEmployee)
        ->getJson(projectTeamStatisticsUrl($tenant))
        ->assertNotFound();

    $mismatchedUrl = "/api/v1/workspaces/{$tenant->workspace->id}/projects/{$foreign->project->id}/statistics/team";
    $this->actingAs($foreign->admin)->getJson($mismatchedUrl)->assertNotFound();
});

it('returns an empty team result for a period without approved entries', function () {
    $tenant = TenantFixture::create();

    $this->actingAs($tenant->admin)
        ->getJson(projectTeamStatisticsUrl($tenant).'?from=2026-08-01&to=2026-08-31')
        ->assertOk()
        ->assertJsonPath('data.period.status', 'approved')
        ->assertJsonPath('data.summary.totalHours', 0)
        ->assertJsonPath('data.summary.overtimeHours', 0)
        ->assertJsonPath('data.summary.overtimeSharePercent', 0)
        ->assertJsonPath('data.summary.contributorsCount', 0)
        ->assertJsonCount(0, 'data.employees');
});

it('builds approved team aggregates and retains historical contributors', function () {
    $tenant = TenantFixture::create();
    $active = User::factory()->forWorkspace($tenant->workspace)->create([
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
    ]);
    $inactive = User::factory()->forWorkspace($tenant->workspace)->create([
        'first_name' => 'Grace',
        'last_name'  => 'Hopper',
    ]);
    $former = User::factory()->forWorkspace($tenant->workspace)->create([
        'first_name' => 'Alan',
        'last_name'  => 'Turing',
    ]);
    $deleted = User::factory()->forWorkspace($tenant->workspace)->create([
        'first_name' => 'Katherine',
        'last_name'  => 'Johnson',
    ]);
    $withoutHours = $tenant->employee();

    $tenant->membership($active, ProjectRole::MANAGER);
    $inactiveMembership = $tenant->membership($inactive, ProjectRole::PARTICIPANT);
    $formerMembership   = $tenant->membership($former, ProjectRole::SENIOR);
    $tenant->membership($deleted, ProjectRole::PARTICIPANT);
    $tenant->membership($withoutHours);

    $approvedActive = createProjectTeamStatisticsTimesheet(
        $tenant,
        $tenant->project,
        $active,
        TimesheetStatus::APPROVED,
        '2026-08-01',
        '2026-08-07',
    );
    $approvedInactive = createProjectTeamStatisticsTimesheet(
        $tenant,
        $tenant->project,
        $inactive,
        TimesheetStatus::APPROVED,
        '2026-08-08',
        '2026-08-14',
    );
    $approvedFormer = createProjectTeamStatisticsTimesheet(
        $tenant,
        $tenant->project,
        $former,
        TimesheetStatus::APPROVED,
        '2026-08-15',
        '2026-08-21',
    );
    $approvedDeleted = createProjectTeamStatisticsTimesheet(
        $tenant,
        $tenant->project,
        $deleted,
        TimesheetStatus::APPROVED,
        '2026-08-22',
        '2026-08-31',
    );

    TimeEntry::factory()->for($approvedActive)->create([
        'work_date'   => '2026-08-01',
        'hours'       => 8,
        'description' => 'Private description',
    ]);
    TimeEntry::factory()->for($approvedActive)->overtime()->create([
        'work_date' => '2026-08-10',
        'hours'     => 4,
    ]);
    TimeEntry::factory()->for($approvedInactive)->create([
        'work_date' => '2026-08-31',
        'hours'     => 6,
    ]);
    TimeEntry::factory()->for($approvedFormer)->create([
        'work_date' => '2026-08-15',
        'hours'     => 3,
    ]);
    TimeEntry::factory()->for($approvedDeleted)->create([
        'work_date' => '2026-08-20',
        'hours'     => 3,
    ]);

    foreach ([TimesheetStatus::SUBMITTED, TimesheetStatus::DRAFT, TimesheetStatus::REJECTED] as $index => $status) {
        $ignored = createProjectTeamStatisticsTimesheet(
            $tenant,
            $tenant->project,
            $active,
            $status,
            '2026-09-0'.($index + 1),
            '2026-09-0'.($index + 1),
        );
        TimeEntry::factory()->for($ignored)->create([
            'work_date' => '2026-08-16',
            'hours'     => 20,
        ]);
    }

    $outside = createProjectTeamStatisticsTimesheet(
        $tenant,
        $tenant->project,
        $active,
        TimesheetStatus::APPROVED,
        '2026-07-01',
        '2026-07-31',
    );
    TimeEntry::factory()->for($outside)->create(['work_date' => '2026-07-31', 'hours' => 20]);

    $otherProject          = Project::factory()->for($tenant->workspace)->create();
    $otherProjectTimesheet = createProjectTeamStatisticsTimesheet(
        $tenant,
        $otherProject,
        $active,
        TimesheetStatus::APPROVED,
        '2026-08-01',
        '2026-08-31',
    );
    TimeEntry::factory()->for($otherProjectTimesheet)->create(['work_date' => '2026-08-10', 'hours' => 30]);

    $foreign          = TenantFixture::create();
    $foreignEmployee  = $foreign->employee();
    $foreignTimesheet = createProjectTeamStatisticsTimesheet(
        $foreign,
        $foreign->project,
        $foreignEmployee,
        TimesheetStatus::APPROVED,
        '2026-08-01',
        '2026-08-31',
    );
    TimeEntry::factory()->for($foreignTimesheet)->create(['work_date' => '2026-08-10', 'hours' => 30]);

    $inactiveMembership->updateOrFail(['active' => false]);
    $formerMembership->deleteOrFail();
    $deleted->deleteOrFail();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($tenant->admin)
        ->getJson(projectTeamStatisticsUrl($tenant).'?from=2026-08-01&to=2026-08-31');
    $statisticsQueryCount = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains((string) $query['query'], 'time_entries'))
        ->count();
    DB::disableQueryLog();

    $response
        ->assertOk()
        ->assertJsonPath('data.period.from', '2026-08-01')
        ->assertJsonPath('data.period.to', '2026-08-31')
        ->assertJsonPath('data.period.status', 'approved')
        ->assertJsonPath('data.summary.totalHours', 24)
        ->assertJsonPath('data.summary.overtimeHours', 4)
        ->assertJsonPath('data.summary.overtimeSharePercent', 16.67)
        ->assertJsonPath('data.summary.contributorsCount', 4)
        ->assertJsonCount(4, 'data.employees')
        ->assertJsonPath('data.employees.0.userId', $active->id)
        ->assertJsonPath('data.employees.0.name', 'Ada Lovelace')
        ->assertJsonPath('data.employees.0.role', ProjectRole::MANAGER->value)
        ->assertJsonPath('data.employees.0.active', true)
        ->assertJsonPath('data.employees.0.hours', 12)
        ->assertJsonPath('data.employees.0.overtimeHours', 4)
        ->assertJsonPath('data.employees.0.sharePercent', 50)
        ->assertJsonPath('data.employees.1.userId', $inactive->id)
        ->assertJsonPath('data.employees.1.active', false)
        ->assertJsonPath('data.employees.2.userId', $former->id)
        ->assertJsonPath('data.employees.2.role', null)
        ->assertJsonPath('data.employees.2.active', false)
        ->assertJsonPath('data.employees.3.userId', $deleted->id)
        ->assertJsonPath('data.employees.3.active', false)
        ->assertJsonMissingPath('data.employees.0.email')
        ->assertJsonMissingPath('data.employees.0.description');

    expect($statisticsQueryCount)->toBe(2)
        ->and(collect($response->json('data.employees'))->pluck('userId'))->not->toContain($withoutHours->id);
});
