<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

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

    expect($crossWorkspaceMemberships)->toBe(0)
        ->and($crossWorkspaceTimesheets)->toBe(0)
        ->and($outOfPeriodEntries)->toBe(0);
});
