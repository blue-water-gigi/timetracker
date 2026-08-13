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

function createProjectStatisticsTimesheet(TenantFixture $tenant, Project $project, User $user, TimesheetStatus $status, string $periodStart, string $periodEnd): Timesheet
{
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

    return $factory->create(['period_start' => $periodStart, 'period_end' => $periodEnd]);
}

function projectStatisticsUrl(TenantFixture $tenant, ?Project $project = null): string
{
    $project ??= $tenant->project;

    return "/api/v1/workspaces/{$tenant->workspace->id}/projects/{$project->id}/statistics";
}

it('requires authentication', function () {
    $tenant = TenantFixture::create();

    $this->actingAsGuest('web')->getJson(projectStatisticsUrl($tenant))->assertUnauthorized();
});

it('allows the owning administrator and an active project member', function () {
    $tenant = TenantFixture::create();
    $member = $tenant->employee();
    $tenant->membership($member);
    $url = projectStatisticsUrl($tenant).'?from=2026-08-01&to=2026-08-03&granularity=day';

    $this->actingAs($tenant->admin)
        ->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.period.status', 'approved')
        ->assertJsonPath('data.summary.totalHours', 0)
        ->assertJsonPath('data.summary.activeMembersCount', 1)
        ->assertJsonCount(3, 'data.timeline')
        ->assertJsonCount(3, 'data.dailyActivity')
        ->assertJsonCount(0, 'data.recentApprovedTimesheets');

    $this->actingAs($member)->getJson($url)->assertOk();
});

it('forbids employees without an active project membership', function () {
    $tenant         = TenantFixture::create();
    $nonMember      = $tenant->employee();
    $inactiveMember = $tenant->employee();
    $tenant->membership($inactiveMember, active: false);

    $this->actingAs($nonMember)->getJson(projectStatisticsUrl($tenant))->assertForbidden();
    $this->actingAs($inactiveMember)->getJson(projectStatisticsUrl($tenant))->assertForbidden();
});

it('hides foreign projects and mismatched workspace project pairs', function () {
    $tenant  = TenantFixture::create();
    $foreign = TenantFixture::create();

    $this->actingAs($tenant->admin)->getJson(projectStatisticsUrl($foreign))->assertNotFound();

    $mismatchedUrl = "/api/v1/workspaces/{$tenant->workspace->id}/projects/{$foreign->project->id}/statistics";
    $this->actingAs($foreign->admin)->getJson($mismatchedUrl)->assertNotFound();
});

it('builds safe approved project aggregates without crossing resource boundaries', function () {
    $tenant           = TenantFixture::create();
    $firstEmployee    = User::factory()->forWorkspace($tenant->workspace)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $secondEmployee   = User::factory()->forWorkspace($tenant->workspace)->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    $inactiveEmployee = $tenant->employee();
    $deletedEmployee  = $tenant->employee();
    $tenant->membership($firstEmployee);
    $tenant->membership($secondEmployee);
    $tenant->membership($inactiveEmployee, active: false);
    $tenant->membership($deletedEmployee);
    $deletedEmployee->deleteOrFail();

    $approvedA = createProjectStatisticsTimesheet($tenant, $tenant->project, $firstEmployee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-07');
    $approvedB = createProjectStatisticsTimesheet($tenant, $tenant->project, $firstEmployee, TimesheetStatus::APPROVED, '2026-08-08', '2026-08-14');
    $approvedC = createProjectStatisticsTimesheet($tenant, $tenant->project, $secondEmployee, TimesheetStatus::APPROVED, '2026-08-15', '2026-08-31');
    $submitted = createProjectStatisticsTimesheet($tenant, $tenant->project, $firstEmployee, TimesheetStatus::SUBMITTED, '2026-08-15', '2026-08-16');
    $draft     = createProjectStatisticsTimesheet($tenant, $tenant->project, $firstEmployee, TimesheetStatus::DRAFT, '2026-08-17', '2026-08-18');
    $rejected  = createProjectStatisticsTimesheet($tenant, $tenant->project, $firstEmployee, TimesheetStatus::REJECTED, '2026-08-19', '2026-08-20');
    $outside   = createProjectStatisticsTimesheet($tenant, $tenant->project, $firstEmployee, TimesheetStatus::APPROVED, '2026-07-01', '2026-07-31');

    TimeEntry::factory()->for($approvedA)->create(['work_date' => '2026-08-01', 'hours' => 8, 'description' => 'Private']);
    TimeEntry::factory()->for($approvedB)->overtime()->create(['work_date' => '2026-08-10', 'hours' => 4]);
    TimeEntry::factory()->for($approvedB)->create(['work_date' => '2026-08-11', 'hours' => 4]);
    TimeEntry::factory()->for($approvedC)->create(['work_date' => '2026-08-31', 'hours' => 8]);
    TimeEntry::factory()->for($submitted)->create(['work_date' => '2026-08-15', 'hours' => 20]);
    TimeEntry::factory()->for($draft)->create(['work_date' => '2026-08-17', 'hours' => 20]);
    TimeEntry::factory()->for($rejected)->create(['work_date' => '2026-08-19', 'hours' => 20]);
    TimeEntry::factory()->for($outside)->create(['work_date' => '2026-07-31', 'hours' => 20]);

    $otherProject  = Project::factory()->for($tenant->workspace)->create();
    $otherApproved = createProjectStatisticsTimesheet($tenant, $otherProject, $firstEmployee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-07');
    TimeEntry::factory()->for($otherApproved)->create(['work_date' => '2026-08-02', 'hours' => 30]);

    $foreign         = TenantFixture::create();
    $foreignEmployee = $foreign->employee();
    $foreignApproved = createProjectStatisticsTimesheet($foreign, $foreign->project, $foreignEmployee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-07');
    TimeEntry::factory()->for($foreignApproved)->create(['work_date' => '2026-08-03', 'hours' => 30]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response             = $this->actingAs($tenant->admin)->getJson(projectStatisticsUrl($tenant).'?from=2026-08-01&to=2026-08-31&granularity=day');
    $statisticsQueryCount = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains((string) $query['query'], 'time_entries') || str_contains((string) $query['query'], 'project_members'))
        ->count();
    DB::disableQueryLog();

    $response
        ->assertOk()
        ->assertJsonPath('data.summary.totalHours', 24)
        ->assertJsonPath('data.summary.overtimeHours', 4)
        ->assertJsonPath('data.summary.overtimeSharePercent', 16.67)
        ->assertJsonPath('data.summary.activeMembersCount', 2)
        ->assertJsonCount(31, 'data.timeline')
        ->assertJsonCount(31, 'data.dailyActivity')
        ->assertJsonCount(3, 'data.recentApprovedTimesheets')
        ->assertJsonMissingPath('data.employees')
        ->assertJsonMissingPath('data.recentApprovedTimesheets.0.description')
        ->assertJsonMissingPath('data.recentApprovedTimesheets.0.email')
        ->assertJsonMissingPath('data.recentApprovedTimesheets.0.reviewComment');

    $timeline = collect($response->json('data.timeline'))->keyBy('bucketStart');
    $daily    = collect($response->json('data.dailyActivity'))->keyBy('date');

    expect($statisticsQueryCount)->toBe(5)
        ->and($timeline['2026-08-01']['hours'])->toBe(8)
        ->and($timeline['2026-08-02']['hours'])->toBe(0)
        ->and($timeline['2026-08-10']['overtimeHours'])->toBe(4)
        ->and($daily['2026-08-11']['hours'])->toBe(4)
        ->and($daily['2026-08-31']['hours'])->toBe(8);
});

it('limits and stably orders recent approved timesheets while retaining deleted authors', function () {
    $tenant     = TenantFixture::create();
    $timesheets = collect();

    foreach (range(1, 6) as $number) {
        $employee  = User::factory()->forWorkspace($tenant->workspace)->create(['first_name' => 'Employee', 'last_name' => sprintf('%02d', $number)]);
        $timesheet = createProjectStatisticsTimesheet($tenant, $tenant->project, $employee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-31');
        $timesheet->forceFill(['reviewed_at' => $number >= 5 ? '2026-09-01 12:00:00' : sprintf('2026-08-%02d 12:00:00', 20 + $number)])->save();
        TimeEntry::factory()->for($timesheet)->create(['work_date' => '2026-08-10', 'hours' => $number]);
        $timesheets->push($timesheet);

        if ($number === 6) {
            $employee->deleteOrFail();
        }
    }

    $expectedIds = $timesheets
        ->sort(fn (Timesheet $left, Timesheet $right): int => [$right->reviewed_at->getTimestamp(), $right->id] <=> [$left->reviewed_at->getTimestamp(), $left->id])
        ->take(5)
        ->pluck('id')
        ->values()
        ->all();

    $response = $this->actingAs($tenant->admin)
        ->getJson(projectStatisticsUrl($tenant).'?from=2026-08-01&to=2026-08-31')
        ->assertOk()
        ->assertJsonCount(5, 'data.recentApprovedTimesheets');

    expect(collect($response->json('data.recentApprovedTimesheets'))->pluck('timesheetId')->all())
        ->toBe($expectedIds)
        ->and($response->json('data.recentApprovedTimesheets.0.userName'))->toBe('Employee 06')
        ->and($response->json('data.recentApprovedTimesheets.0.approvedAt'))->toBe('2026-09-01T12:00:00+00:00');
});
