<?php

declare(strict_types=1);

namespace App\Queries\Statistics;

use App\Contracts\Queries\Statistics\GetProjectStatistics;
use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\Workspace;
use App\Support\Statistics\InteractsWithActivity;
use App\Support\Statistics\InteractsWithBuckets;
use App\Support\Statistics\InteractsWithHours;
use App\Support\Statistics\StatisticsPeriod;
use Carbon\CarbonImmutable;
use DB;
use Illuminate\Database\Query\Builder;

class EloquentGetProjectStatistics implements GetProjectStatistics
{
    use InteractsWithActivity, InteractsWithBuckets, InteractsWithHours;

    private const int RECENT_TS_LIMIT = 5;

    /**
     * {@inheritDoc}
     */
    public function execute(Workspace $workspace, Project $project, StatisticsPeriod $period): array
    {
        $summary = $this->summary($workspace, $project, $period);

        return [
            'period'                   => [...$period->toArray(), 'status' => TimesheetStatus::APPROVED->value],
            'summary'                  => $summary,
            'timeline'                 => $this->timeline($workspace, $project, $period),
            'dailyActivity'            => $this->dailyActivity($workspace, $project, $period),
            'recentApprovedTimesheets' => $this->recentApprovedTimesheets($workspace, $project, $period),
        ];
    }

    private function approved(Workspace $workspace, Project $project, StatisticsPeriod $period): Builder
    {
        return DB::table('timesheets AS t')
            ->join('time_entries AS e', 'e.timesheet_id', '=', 't.id')
            ->where('t.workspace_id', $workspace->getKey())
            ->where('t.project_id', $project->getKey())
            ->where('t.status', TimesheetStatus::APPROVED->value)
            ->whereBetween('e.work_date', [
                $period->from->toDateString(),
                $period->to->toDateString(),
            ]);
    }

    private function activeMembersCount(Workspace $workspace, Project $project): int
    {
        return DB::table('project_members AS pm')
            ->join('projects AS p', 'p.id', '=', 'pm.project_id')
            ->join('users as u', 'u.id', '=', 'pm.user_id')
            ->where('p.workspace_id', $workspace->getKey())
            ->where('pm.project_id', $project->getKey())
            ->where('pm.active', true)
            ->whereNull('u.deleted_at')
            ->count();
    }

    private function summary(Workspace $workspace, Project $project, StatisticsPeriod $period): array
    {
        $row = $this->approved($workspace, $project, $period)
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS total_hours')
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
                    WHERE e.is_overtime = TRUE
                ), 0) AS overtime_hours')
            ->first();

        $totalHours    = $this->hours(data_get($row, 'total_hours'));
        $overtimeHours = $this->hours(data_get($row, 'overtime_hours'));

        return [
            'totalHours'           => $totalHours,
            'overtimeHours'        => $overtimeHours,
            'overtimeSharePercent' => $totalHours > 0
                ? $this->hours($overtimeHours / $totalHours * 100)
                : 0.0,
            'activeMembersCount' => $this->activeMembersCount($workspace, $project),
        ];
    }

    private function timeline(Workspace $workspace, Project $project, StatisticsPeriod $period): array
    {
        $granularityExpression = $this->matchDateTruncBucket($period->granularity);

        $rows = $this->approved($workspace, $project, $period)
            ->selectRaw("$granularityExpression AS bucket_start")
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS hours')
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
                    WHERE e.is_overtime = TRUE
                ), 0) AS overtime_hours')
            ->groupByRaw($granularityExpression)
            ->orderBy('bucket_start')
            ->get()
            ->keyBy(fn (object $row): string => CarbonImmutable::parse(
                (string) data_get($row, 'bucket_start'))->toDateString()
            );

        return array_map(function (CarbonImmutable $bucket) use ($rows): array {
            $key = $bucket->toDateString();
            $row = $rows->get($key);

            return [
                'bucketStart'   => $key,
                'hours'         => $this->hours(data_get($row, 'hours')),
                'overtimeHours' => $this->hours(data_get($row, 'overtime_hours')),
            ];
        }, $period->bucketStarts());
    }

    private function dailyActivity(Workspace $workspace, Project $project, StatisticsPeriod $period): array
    {
        $rows = $this->approved($workspace, $project, $period)
            ->select('e.work_date')
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS hours')
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
                    WHERE e.is_overtime = TRUE
                ), 0) AS overtime_hours')
            ->groupByRaw('e.work_date')
            ->orderBy('e.work_date')
            ->get()
            ->keyBy(fn (object $row): string => CarbonImmutable::parse(
                (string) data_get($row, 'work_date'))->toDateString()
            );

        return $this->activity($period, $rows);
    }

    private function recentApprovedTimesheets(Workspace $workspace, Project $project, StatisticsPeriod $period): array
    {
        return $this->approved($workspace, $project, $period)
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->selectRaw('t.id AS timesheet_id, t.period_start, t.period_end, t.reviewed_at')
            ->selectRaw("u.id AS user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name")
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS hours')
            ->groupBy(
                't.id',
                't.period_start',
                't.period_end',
                't.reviewed_at',
                'u.id',
                'u.first_name',
                'u.last_name'
            )
            ->orderByDesc('t.reviewed_at')
            ->orderByDesc('t.id')
            ->limit(self::RECENT_TS_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'timesheetId' => (int) $row->timesheet_id,
                'userId'      => (int) $row->user_id,
                'userName'    => trim($row->user_name),
                'periodStart' => CarbonImmutable::parse($row->period_start)->toDateString(),
                'periodEnd'   => CarbonImmutable::parse($row->period_end)->toDateString(),
                'hours'       => $this->hours($row->hours),
                'approvedAt'  => data_get($row, 'reviewed_at') !== null
                    ? CarbonImmutable::parse($row->reviewed_at)->toIso8601String()
                    : null,
            ])
            ->values()
            ->all();
    }
}
