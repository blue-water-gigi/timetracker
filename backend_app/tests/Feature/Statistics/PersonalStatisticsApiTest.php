<?php

declare(strict_types=1);

use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use Database\Factories\TimesheetFactory;
use Tests\Support\TenantFixture;

function createStatisticsTimesheet(
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
    $factory = Timesheet::factory()
        ->for($project)
        ->for($user);

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

test('guest gets 401 error', function () {
    $tenant = TenantFixture::create();

    $this->actingAsGuest('web')
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics/me")
        ->assertUnauthorized();
});

it('returns an empty personal statistics contract for an employee', function () {
    $tenant   = TenantFixture::create();
    $employee = $tenant->employee();

    $this->actingAs($employee)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics/me?from=2026-08-01&to=2026-08-03&granularity=day")
        ->assertOk()
        ->assertJsonPath('data.period.from', '2026-08-01')
        ->assertJsonPath('data.period.to', '2026-08-03')
        ->assertJsonPath('data.period.granularity', 'day')
        ->assertJsonPath('data.period.status', 'approved')
        ->assertJsonPath('data.summary.totalHours', 0)
        ->assertJsonPath('data.summary.previousHours', 0)
        ->assertJsonPath('data.summary.deltaHours', 0)
        ->assertJsonPath('data.summary.deltaPercent', null)
        ->assertJsonPath('data.summary.overtimeHours', 0)
        ->assertJsonPath('data.summary.overtimeSharePercent', 0)
        ->assertJsonPath('data.summary.pendingHours', 0)
        ->assertJsonCount(3, 'data.timeline')
        ->assertJsonCount(3, 'data.dailyActivity')
        ->assertJsonCount(0, 'data.projects')
        ->assertJsonPath('data.timeline.0.bucketStart', '2026-08-01')
        ->assertJsonPath('data.timeline.0.hours', 0)
        ->assertJsonPath('data.dailyActivity.2.date', '2026-08-03')
        ->assertJsonPath('data.dailyActivity.2.hours', 0);
});

it('hides a foreign workspace from employees and administrators', function () {
    $tenant  = TenantFixture::create();
    $foreign = TenantFixture::create();
    $url     = "/api/v1/workspaces/{$foreign->workspace->id}/statistics/me";

    $this->actingAs($tenant->employee())
        ->getJson($url)
        ->assertNotFound();

    $this->actingAs($tenant->admin)
        ->getJson($url)
        ->assertNotFound();
});

it('does not include employee hours in the administrator personal statistics', function () {
    $tenant    = TenantFixture::create();
    $employee  = $tenant->employee();
    $timesheet = createStatisticsTimesheet(
        $tenant,
        $tenant->project,
        $employee,
        TimesheetStatus::APPROVED,
        '2026-08-01',
        '2026-08-07',
    );

    TimeEntry::factory()->for($timesheet)->create([
        'work_date' => '2026-08-03',
        'hours'     => 8,
    ]);

    $this->actingAs($tenant->admin)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics/me?from=2026-08-01&to=2026-08-07")
        ->assertOk()
        ->assertJsonPath('data.summary.totalHours', 0)
        ->assertJsonPath('data.summary.pendingHours', 0)
        ->assertJsonCount(0, 'data.projects');
});

it('builds personal aggregates and excludes unrelated entries', function () {
    $tenant        = TenantFixture::create();
    $employee      = $tenant->employee();
    $otherEmployee = $tenant->employee();
    $firstProject  = $tenant->project;
    $firstProject->update([
        'name'         => 'Alpha',
        'period_start' => null,
        'period_end'   => null,
    ]);
    $secondProject = Project::factory()->for($tenant->workspace)->create([
        'name'         => 'Beta',
        'period_start' => null,
        'period_end'   => null,
    ]);

    $firstApproved         = createStatisticsTimesheet($tenant, $firstProject, $employee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-07');
    $secondApproved        = createStatisticsTimesheet($tenant, $secondProject, $employee, TimesheetStatus::APPROVED, '2026-08-08', '2026-08-14');
    $submitted             = createStatisticsTimesheet($tenant, $firstProject, $employee, TimesheetStatus::SUBMITTED, '2026-08-15', '2026-08-21');
    $draft                 = createStatisticsTimesheet($tenant, $firstProject, $employee, TimesheetStatus::DRAFT, '2026-08-22', '2026-08-23');
    $rejected              = createStatisticsTimesheet($tenant, $firstProject, $employee, TimesheetStatus::REJECTED, '2026-08-24', '2026-08-25');
    $previousApproved      = createStatisticsTimesheet($tenant, $firstProject, $employee, TimesheetStatus::APPROVED, '2026-07-01', '2026-07-31');
    $otherEmployeeApproved = createStatisticsTimesheet($tenant, $firstProject, $otherEmployee, TimesheetStatus::APPROVED, '2026-08-01', '2026-08-07');

    TimeEntry::factory()->for($firstApproved)->create(['work_date' => '2026-08-03', 'hours' => 8]);
    TimeEntry::factory()->for($firstApproved)->overtime()->create(['work_date' => '2026-08-04', 'hours' => 2]);
    TimeEntry::factory()->for($secondApproved)->create(['work_date' => '2026-08-10', 'hours' => 4]);
    TimeEntry::factory()->for($submitted)->create(['work_date' => '2026-08-16', 'hours' => 5]);
    TimeEntry::factory()->for($draft)->create(['work_date' => '2026-08-22', 'hours' => 7]);
    TimeEntry::factory()->for($rejected)->create(['work_date' => '2026-08-24', 'hours' => 9]);
    TimeEntry::factory()->for($previousApproved)->create(['work_date' => '2026-07-15', 'hours' => 6]);
    TimeEntry::factory()->for($otherEmployeeApproved)->create(['work_date' => '2026-08-05', 'hours' => 20]);

    $response = $this->actingAs($employee)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/statistics/me?from=2026-08-01&to=2026-08-31&granularity=day")
        ->assertOk()
        ->assertJsonPath('data.summary.totalHours', 14)
        ->assertJsonPath('data.summary.previousHours', 6)
        ->assertJsonPath('data.summary.deltaHours', 8)
        ->assertJsonPath('data.summary.deltaPercent', 133.33)
        ->assertJsonPath('data.summary.overtimeHours', 2)
        ->assertJsonPath('data.summary.overtimeSharePercent', 14.29)
        ->assertJsonPath('data.summary.pendingHours', 5)
        ->assertJsonCount(31, 'data.timeline')
        ->assertJsonCount(31, 'data.dailyActivity')
        ->assertJsonCount(2, 'data.projects')
        ->assertJsonPath('data.projects.0.projectId', $firstProject->id)
        ->assertJsonPath('data.projects.0.name', 'Alpha')
        ->assertJsonPath('data.projects.0.hours', 10)
        ->assertJsonPath('data.projects.0.sharePercent', 71.43)
        ->assertJsonPath('data.projects.1.projectId', $secondProject->id)
        ->assertJsonPath('data.projects.1.name', 'Beta')
        ->assertJsonPath('data.projects.1.hours', 4)
        ->assertJsonPath('data.projects.1.sharePercent', 28.57);

    $timeline      = collect($response->json('data.timeline'))->keyBy('bucketStart');
    $dailyActivity = collect($response->json('data.dailyActivity'))->keyBy('date');

    expect($timeline['2026-08-01']['hours'])->toBe(0)
        ->and($timeline['2026-08-03']['hours'])->toBe(8)
        ->and($timeline['2026-08-04']['overtimeHours'])->toBe(2)
        ->and($timeline['2026-08-10']['hours'])->toBe(4)
        ->and($timeline['2026-08-31']['hours'])->toBe(0)
        ->and($dailyActivity['2026-08-01']['hours'])->toBe(0)
        ->and($dailyActivity['2026-08-03']['hours'])->toBe(8)
        ->and($dailyActivity['2026-08-04']['overtimeHours'])->toBe(2)
        ->and($dailyActivity['2026-08-16']['hours'])->toBe(0)
        ->and($dailyActivity['2026-08-31']['hours'])->toBe(0);
});
