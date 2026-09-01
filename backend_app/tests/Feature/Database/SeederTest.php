<?php

declare(strict_types=1);

use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;

it('seeds a complete and tenant-consistent development graph', function () {
    $this->seed();

    expect(Project::query()->count())->toBeGreaterThan(0)
        ->and(ProjectMember::query()->count())->toBeGreaterThan(0)
        ->and(Timesheet::query()->count())->toBeGreaterThan(0)
        ->and(TimeEntry::query()->count())->toBeGreaterThan(0);

    $crossWorkspaceMemberships = DB::table('project_members')
        ->join('projects', 'projects.id', '=', 'project_members.project_id')
        ->join('users', 'users.id', '=', 'project_members.user_id')
        ->whereColumn('projects.workspace_id', '!=', 'users.workspace_id')
        ->count();

    $crossWorkspaceTimesheets = DB::table('timesheets')
        ->join('projects', 'projects.id', '=', 'timesheets.project_id')
        ->whereColumn('projects.workspace_id', '!=', 'timesheets.workspace_id')
        ->count();

    $outOfPeriodEntries = DB::table('time_entries')
        ->join('timesheets', 'timesheets.id', '=', 'time_entries.timesheet_id')
        ->where(function ($query): void {
            $query->whereColumn('time_entries.work_date', '<', 'timesheets.period_start')
                ->orWhereColumn('time_entries.work_date', '>', 'timesheets.period_end');
        })
        ->count();

    $timesheetsWithoutActiveMembership = DB::table('timesheets')
        ->whereNotExists(function ($query): void {
            $query->selectRaw('1')
                ->from('project_members')
                ->whereColumn('project_members.project_id', 'timesheets.project_id')
                ->whereColumn('project_members.user_id', 'timesheets.user_id')
                ->where('project_members.active', true);
        })
        ->count();

    $invalidWorkflowMetadata = Timesheet::query()
        ->get()
        ->filter(
            fn (Timesheet $timesheet): bool => match ($timesheet->status) {
                TimesheetStatus::DRAFT     => $timesheet->submitted_at                                                                                                                                                                                                                                                             !== null || $timesheet->reviewed_at !== null || $timesheet->reviewed_by_user_id !== null,
                TimesheetStatus::SUBMITTED => $timesheet->submitted_at === null                                                                                                                                                                                                                                                             || $timesheet->reviewed_at !== null || $timesheet->reviewed_by_user_id !== null,
                TimesheetStatus::APPROVED  => $timesheet->submitted_at === null                                                                                                                                                                                                                                                             || $timesheet->reviewed_at === null || $timesheet->reviewed_by_user_id === null,
                TimesheetStatus::REJECTED  => $timesheet->submitted_at === null                                                                                                                                                                                                                                                             || $timesheet->reviewed_at === null || $timesheet->reviewed_by_user_id === null || trim((string) $timesheet->review_comment) === '',
            },
        )
        ->count();

    foreach (TimesheetStatus::cases() as $status) {
        expect(Timesheet::query()->where('status', $status->value)->exists())->toBeTrue();
    }

    expect($crossWorkspaceMemberships)->toBe(0)
        ->and($crossWorkspaceTimesheets)->toBe(0)
        ->and($outOfPeriodEntries)->toBe(0)
        ->and($timesheetsWithoutActiveMembership)->toBe(0)
        ->and($invalidWorkflowMetadata)->toBe(0);
});

it('builds coherent factory states for timesheets and time entries', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $periodStart = today()->startOfWeek()->addWeeks(10);

    $period = fn (int $offset): array => [
        'period_start' => $periodStart->copy()->addWeeks($offset),
        'period_end'   => $periodStart->copy()->addWeeks($offset)->endOfWeek(),
    ];

    $draft = Timesheet::factory()
        ->for($tenant->project)
        ->for($author)
        ->create($period(0));
    $submitted = Timesheet::factory()
        ->for($tenant->project)
        ->for($author)
        ->submitted()
        ->create($period(1));
    $approved = Timesheet::factory()
        ->for($tenant->project)
        ->for($author)
        ->approved($tenant->admin)
        ->create($period(2));
    $rejected = Timesheet::factory()
        ->for($tenant->project)
        ->for($author)
        ->rejected($tenant->admin)
        ->create($period(3));
    $entry = TimeEntry::factory()->for($draft)->overtime()->create();

    expect($draft->submitted_at)->toBeNull()
        ->and($submitted->status)->toBe(TimesheetStatus::SUBMITTED)
        ->and($submitted->submitted_at)->not->toBeNull()
        ->and($submitted->reviewed_by_user_id)->toBeNull()
        ->and($approved->status)->toBe(TimesheetStatus::APPROVED)
        ->and($approved->reviewed_by_user_id)->toBe($tenant->admin->id)
        ->and($approved->reviewed_at)->not->toBeNull()
        ->and($rejected->status)->toBe(TimesheetStatus::REJECTED)
        ->and($rejected->review_comment)->toBe('Changes requested.')
        ->and($entry->is_overtime)->toBeTrue();

    expect($entry->hours)->toMatch('/^\d+\.\d{2}$/');
});
