<?php

declare(strict_types=1);

use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use Database\Factories\TimesheetFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;

function createWorkspaceStatisticsTimesheet(
    TenantFixture $tenant,
    Project $project,
    User $user,
    TimesheetStatus $status,
    string $periodStart,
    string $periodEnd,
): Timesheet {
    if (! ProjectMember::query()
        ->whereBelongsTo($project)
        ->whereBelongsTo($user)
        ->exists()) {
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

it('requires authentication', function () {
    $tenant = TenantFixture::create();

    $this->actingAsGuest('web')
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics")
        ->assertUnauthorized();
});

it('returns an empty workspace statistics contract to the owning administrator', function () {
    $tenant = TenantFixture::create();

    $this->actingAs($tenant->admin)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics?from=2026-08-01&to=2026-08-03&granularity=day")
        ->assertOk()
        ->assertJsonPath('data.period.from', '2026-08-01')
        ->assertJsonPath('data.period.to', '2026-08-03')
        ->assertJsonPath('data.period.granularity', 'day')
        ->assertJsonPath('data.period.status', 'approved')
        ->assertJsonPath('data.summary.totalHours', 0)
        ->assertJsonPath('data.summary.overtimeHours', 0)
        ->assertJsonPath('data.summary.overtimeSharePercent', 0)
        ->assertJsonCount(3, 'data.timeline')
        ->assertJsonCount(0, 'data.projects')
        ->assertJsonCount(0, 'data.employees')
        ->assertJsonPath('data.timeline.0.bucketStart', '2026-08-01')
        ->assertJsonPath('data.timeline.0.hours', 0)
        ->assertJsonPath('data.timeline.2.bucketStart', '2026-08-03')
        ->assertJsonPath('data.timeline.2.overtimeHours', 0);
});

it('forbids an employee from viewing their workspace summary', function () {
    $tenant = TenantFixture::create();

    $this->actingAs($tenant->employee())
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics")
        ->assertForbidden();
});

it('hides a foreign workspace from employees and administrators', function () {
    $tenant  = TenantFixture::create();
    $foreign = TenantFixture::create();
    $url     = "/api/v1/workspaces/{$foreign->workspace->id}/statistics";

    $this->actingAs($tenant->employee())->getJson($url)->assertNotFound();
    $this->actingAs($tenant->admin)->getJson($url)->assertNotFound();
});

it('builds approved workspace aggregates without crossing tenant or period boundaries', function () {
    $tenant        = TenantFixture::create();
    $firstEmployee = User::factory()->forWorkspace($tenant->workspace)->create([
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
    ]);
    $secondEmployee = User::factory()->forWorkspace($tenant->workspace)->create([
        'first_name' => 'Grace',
        'last_name'  => 'Hopper',
    ]);
    $firstProject = $tenant->project;
    $firstProject->update(['name' => 'Alpha', 'period_start' => null, 'period_end' => null]);
    $secondProject = Project::factory()->for($tenant->workspace)->create([
        'name'         => 'Beta',
        'period_start' => null,
        'period_end'   => null,
    ]);

    $firstApproved  = createWorkspaceStatisticsTimesheet($tenant, $firstProject, $firstEmployee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-07');
    $secondApproved = createWorkspaceStatisticsTimesheet($tenant, $secondProject, $firstEmployee, TimesheetStatus::APPROVED, '2026-08-08', '2026-08-14');
    $thirdApproved  = createWorkspaceStatisticsTimesheet($tenant, $firstProject, $secondEmployee, TimesheetStatus::APPROVED, '2026-08-15', '2026-08-21');
    $fourthApproved = createWorkspaceStatisticsTimesheet($tenant, $secondProject, $secondEmployee, TimesheetStatus::APPROVED, '2026-08-22', '2026-08-31');
    $submitted      = createWorkspaceStatisticsTimesheet($tenant, $firstProject, $firstEmployee, TimesheetStatus::SUBMITTED, '2026-08-08', '2026-08-09');
    $draft          = createWorkspaceStatisticsTimesheet($tenant, $firstProject, $firstEmployee, TimesheetStatus::DRAFT, '2026-08-12', '2026-08-13');
    $rejected       = createWorkspaceStatisticsTimesheet($tenant, $firstProject, $firstEmployee, TimesheetStatus::REJECTED, '2026-08-14', '2026-08-15');
    $outsidePeriod  = createWorkspaceStatisticsTimesheet($tenant, $firstProject, $firstEmployee, TimesheetStatus::APPROVED, '2026-07-01', '2026-07-31');

    TimeEntry::factory()->for($firstApproved)->create(['work_date' => '2026-08-01', 'hours' => 8]);
    TimeEntry::factory()->for($secondApproved)->overtime()->create(['work_date' => '2026-08-10', 'hours' => 4]);
    TimeEntry::factory()->for($secondApproved)->create(['work_date' => '2026-08-11', 'hours' => 4]);
    TimeEntry::factory()->for($thirdApproved)->create(['work_date' => '2026-08-20', 'hours' => 6]);
    TimeEntry::factory()->for($fourthApproved)->create(['work_date' => '2026-08-31', 'hours' => 2]);
    TimeEntry::factory()->for($submitted)->create(['work_date' => '2026-08-08', 'hours' => 20]);
    TimeEntry::factory()->for($draft)->create(['work_date' => '2026-08-12', 'hours' => 20]);
    TimeEntry::factory()->for($rejected)->create(['work_date' => '2026-08-14', 'hours' => 20]);
    TimeEntry::factory()->for($outsidePeriod)->create(['work_date' => '2026-07-31', 'hours' => 20]);

    $foreign         = TenantFixture::create();
    $foreignEmployee = $foreign->employee();
    $foreignApproved = createWorkspaceStatisticsTimesheet($foreign, $foreign->project, $foreignEmployee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-07');
    TimeEntry::factory()->for($foreignApproved)->create(['work_date' => '2026-08-02', 'hours' => 30]);

    $secondProject->deleteOrFail();
    $secondEmployee->deleteOrFail();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($tenant->admin)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics?from=2026-08-01&to=2026-08-31&granularity=day");
    $statisticsQueryCount = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains((string) $query['query'], 'time_entries'))
        ->count();
    DB::disableQueryLog();

    $response
        ->assertOk()
        ->assertJsonPath('data.period.status', 'approved')
        ->assertJsonPath('data.summary.totalHours', 24)
        ->assertJsonPath('data.summary.overtimeHours', 4)
        ->assertJsonPath('data.summary.overtimeSharePercent', 16.67)
        ->assertJsonCount(31, 'data.timeline')
        ->assertJsonCount(2, 'data.projects')
        ->assertJsonCount(2, 'data.employees')
        ->assertJsonPath('data.projects.0.projectId', $firstProject->id)
        ->assertJsonPath('data.projects.0.name', 'Alpha')
        ->assertJsonPath('data.projects.0.hours', 14)
        ->assertJsonPath('data.projects.0.sharePercent', 58.33)
        ->assertJsonPath('data.projects.1.projectId', $secondProject->id)
        ->assertJsonPath('data.projects.1.name', 'Beta')
        ->assertJsonPath('data.projects.1.hours', 10)
        ->assertJsonPath('data.projects.1.sharePercent', 41.67)
        ->assertJsonPath('data.employees.0.userId', $firstEmployee->id)
        ->assertJsonPath('data.employees.0.name', 'Ada Lovelace')
        ->assertJsonPath('data.employees.0.hours', 16)
        ->assertJsonPath('data.employees.0.overtimeHours', 4)
        ->assertJsonPath('data.employees.1.userId', $secondEmployee->id)
        ->assertJsonPath('data.employees.1.name', 'Grace Hopper')
        ->assertJsonPath('data.employees.1.hours', 8)
        ->assertJsonPath('data.employees.1.overtimeHours', 0);

    $timeline = collect($response->json('data.timeline'))->keyBy('bucketStart');

    expect($statisticsQueryCount)->toBe(4)
        ->and($timeline['2026-08-01']['hours'])->toBe(8)
        ->and($timeline['2026-08-02']['hours'])->toBe(0)
        ->and($timeline['2026-08-10']['hours'])->toBe(4)
        ->and($timeline['2026-08-10']['overtimeHours'])->toBe(4)
        ->and($timeline['2026-08-31']['hours'])->toBe(2);
});

it('limits equal-hour project and employee rankings with stable ordering', function () {
    $tenant      = TenantFixture::create();
    $projectIds  = [];
    $employeeIds = [];

    foreach (range(1, 11) as $number) {
        $project = Project::factory()->for($tenant->workspace)->create([
            'name' => sprintf('Project %02d', $number),
        ]);
        $employee = User::factory()->forWorkspace($tenant->workspace)->create([
            'first_name' => 'Employee',
            'last_name'  => sprintf('%02d', $number),
        ]);
        $timesheet = createWorkspaceStatisticsTimesheet(
            $tenant,
            $project,
            $employee,
            TimesheetStatus::APPROVED,
            '2026-08-01',
            '2026-08-31',
        );
        TimeEntry::factory()->for($timesheet)->create([
            'work_date' => '2026-08-10',
            'hours'     => 1,
        ]);

        $projectIds[]  = $project->id;
        $employeeIds[] = $employee->id;
    }

    $response = $this->actingAs($tenant->admin)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics?from=2026-08-01&to=2026-08-31")
        ->assertOk()
        ->assertJsonPath('data.summary.totalHours', 11)
        ->assertJsonCount(10, 'data.projects')
        ->assertJsonCount(10, 'data.employees');

    expect(collect($response->json('data.projects'))->pluck('projectId')->all())
        ->toBe(array_slice($projectIds, 0, 10))
        ->and(collect($response->json('data.employees'))->pluck('userId')->all())
        ->toBe(array_slice($employeeIds, 0, 10));
});
