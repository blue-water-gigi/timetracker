<?php

declare(strict_types=1);

namespace App\Queries\Statistics;

use App\Contracts\Queries\Statistics\GetProjectTeamStatistics;
use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\Workspace;
use App\Support\Statistics\InteractsWithHours;
use App\Support\Statistics\StatisticsPeriod;
use DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

class EloquentGetProjectTeamStatistics implements GetProjectTeamStatistics
{
    use InteractsWithHours;

    /**
     * {@inheritDoc}
     */
    public function execute(Workspace $workspace, Project $project, StatisticsPeriod $period): array
    {
        $summary = $this->summary($workspace, $project, $period);

        return [
            'period'    => [...$period->toArray(), 'status' => TimesheetStatus::APPROVED->value],
            'summary'   => $summary,
            'employees' => $this->employees($workspace, $project, $period, $summary['totalHours']),
        ];
    }

    private function approved(Workspace $workspace, Project $project, StatisticsPeriod $period): Builder
    {
        return DB::table('time_entries AS e')
            ->join('timesheets AS t', 't.id', '=', 'e.timesheet_id')
            ->where('t.workspace_id', $workspace->getKey())
            ->where('t.project_id', $project->getKey())
            ->where('t.status', TimesheetStatus::APPROVED->value)
            ->whereBetween('e.work_date', [
                $period->from->toDateString(),
                $period->to->toDateString(),
            ]);
    }

    private function summary(Workspace $workspace, Project $project, StatisticsPeriod $period): array
    {
        $row = $this->approved($workspace, $project, $period)
            ->selectRaw('COALESCE(SUM(e.hours), 0) AS total_hours')
            ->selectRaw(
                'COALESCE(SUM(e.hours) FILTER (
                    WHERE e.is_overtime = true
                ), 0) AS overtime_hours'
            )
            ->selectRaw('COUNT(DISTINCT t.user_id) AS contributors_count')
            ->first();

        $totalHours    = $this->hours(data_get($row, 'total_hours'));
        $overtimeHours = $this->hours(data_get($row, 'overtime_hours'));

        return [
            'totalHours'           => $totalHours,
            'overtimeHours'        => $overtimeHours,
            'overtimeSharePercent' => $totalHours > 0
                ? round($overtimeHours / $totalHours * 100, 2)
                : 0.0,
            'contributorsCount' => (int) data_get($row, 'contributors_count'),
        ];
    }

    private function employees(
        Workspace $workspace,
        Project $project,
        StatisticsPeriod $period,
        float $totalHours): array
    {
        return $this->approved($workspace, $project, $period)
            ->join('users AS u', 'u.id', '=', 't.user_id')
            ->leftJoin('project_members as pm', function (JoinClause $join): void {
                // callback for chaining clauses
                $join->on('pm.project_id', '=', 't.project_id')
                    ->on('pm.user_id', '=', 't.user_id');
            })
            ->selectRaw(
                "u.id AS user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS name,
                u.deleted_at,
                pm.project_role,
                pm.active as pm_active",
            )
            ->selectRaw('SUM(e.hours) AS hours')
            ->selectRaw('COALESCE(SUM(e.hours) FILTER (
                    WHERE e.is_overtime = TRUE
                ), 0) AS overtime_hours')
            ->groupBy(
                'u.id',
                'u.first_name',
                'u.last_name',
                'u.deleted_at',
                'pm.project_role',
                'pm.active')
            ->orderByDesc('hours')
            ->orderBy('u.id')
            ->get()
            ->map(function (object $row) use ($totalHours): array {
                $hours    = $this->hours(data_get($row, 'hours'));
                $role     = data_get($row, 'project_role');
                $pmActive = (bool) data_get($row, 'pm_active');

                return [
                    'userId'        => (int) data_get($row, 'user_id'),
                    'name'          => trim((string) data_get($row, 'name')),
                    'role'          => $role !== null ? (string) $role : null,
                    'active'        => $pmActive && data_get($row, 'deleted_at') === null,
                    'hours'         => $hours,
                    'overtimeHours' => $this->hours(data_get($row, 'overtime_hours')),
                    'sharePercent'  => $totalHours > 0 ? $this->hours($hours / $totalHours * 100) : 0.0,
                ];
            })
            ->values()
            ->all();
    }
}
