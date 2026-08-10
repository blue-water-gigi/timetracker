<?php

declare(strict_types=1);

namespace App\Queries\Statistics;

use App\Contracts\Queries\Statistics\GetWorkspaceStatistics;
use App\Enums\TimesheetStatus;
use App\Models\Workspace;
use App\Support\Statistics\InteractsWithBuckets;
use App\Support\Statistics\InteractsWithHours;
use App\Support\Statistics\StatisticsPeriod;
use Carbon\CarbonImmutable;
use DB;
use Illuminate\Database\Query\Builder;

/** PostgreSQL only */
class EloquentGetWorkspaceStatistics implements GetWorkspaceStatistics
{
    protected const int TOP_LIMIT = 10;

    use InteractsWithBuckets, InteractsWithHours;

    /**
     * {@inheritDoc}
     */
    public function execute(Workspace $workspace, StatisticsPeriod $period): array
    {
        $summary = $this->summary($workspace, $period);

        return [
            'period'    => [...$period->toArray(), 'status' => TimesheetStatus::APPROVED->value],
            'summary'   => $summary,
            'timeline'  => $this->timeline($workspace, $period),
            'projects'  => $this->projects($workspace, $period, $summary['totalHours']),
            'employees' => $this->employees($workspace, $period),
        ];
    }

    private function approved(Workspace $workspace, StatisticsPeriod $period): Builder
    {
        return DB::table('time_entries AS e')
            ->join('timesheets AS t', 't.id', '=', 'e.timesheet_id')
            ->where('t.workspace_id', $workspace->getKey())
            ->where('t.status', TimesheetStatus::APPROVED->value)
            ->whereBetween('e.work_date', [
                $period->from->toDateString(),
                $period->to->toDateString(),
            ]);
    }

    /**
     * @return array{
     *     totalHours: float,
     *     overtimeHours: float,
     *     overtimeSharePercent: float,
     * }
     */
    private function summary(Workspace $workspace, StatisticsPeriod $period): array
    {
        $row = $this->approved($workspace, $period)
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS total_hours')
            ->selectRaw(
                'COALESCE (SUM(e.hours) FILTER (
                WHERE e.is_overtime = TRUE
                ), 0) AS overtime_hours'
            )
            ->first();

        $totalHours    = $this->hours(data_get($row, 'total_hours'));
        $overtimeHours = $this->hours(data_get($row, 'overtime_hours'));

        return [
            'totalHours'           => $totalHours,
            'overtimeHours'        => $overtimeHours,
            'overtimeSharePercent' => $totalHours > 0
                ? $this->hours($overtimeHours / $totalHours * 100)
                : 0.0,
        ];
    }

    /**
     * @return array<int, array<string, float|string>>
     */
    private function timeline(Workspace $workspace, StatisticsPeriod $period): array
    {
        $granularityExpression = $this->matchDateTruncBucket($period->granularity);

        $rows = $this->approved($workspace, $period)
            ->selectRaw("$granularityExpression AS bucket_start")
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS hours')
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
               WHERE e.is_overtime = TRUE
                ), 0) AS overtime_hours')
            ->groupByRaw($granularityExpression)
            ->orderByRaw('bucket_start')
            ->get()
            ->keyBy(
                fn (object $row): string => CarbonImmutable::parse(
                    (string) data_get($row, 'bucket_start')
                )->toDateString()
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

    /**
     * Get top 10 projects depending on hours mark
     *
     * @return array<int, array<string, mixed>>
     */
    private function projects(Workspace $workspace, StatisticsPeriod $period, float $totalHours): array
    {
        return $this->approved($workspace, $period)
            ->join('projects', 'projects.id', '=', 't.project_id')
            ->select(['projects.id AS project_id', 'projects.name AS project_name'])
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS hours')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('hours')
            ->orderBy('projects.id')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (object $row) => [
                'projectId'    => (int) $row->project_id,
                'name'         => (string) $row->project_name,
                'hours'        => $this->hours($row->hours),
                'sharePercent' => $totalHours > 0
                    ? $this->hours($row->hours / $totalHours * 100)
                    : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Get top 10 employees depending on hours mark
     *
     * @return array<int, array<string, mixed>>
     */
    private function employees(Workspace $workspace, StatisticsPeriod $period): array
    {
        return $this->approved($workspace, $period)
            ->join('users', 'users.id', '=', 't.user_id')
            ->select(['users.id AS user_id'])
            ->selectRaw("CONCAT(users.first_name, ' ', users.last_name) AS name")
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS hours')
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (WHERE e.is_overtime = TRUE), 0) AS overtime_hours')
            ->groupBy('users.id', 'users.first_name', 'users.last_name')
            ->orderByDesc('hours')
            ->orderBy('users.id')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'userId'        => (int) $row->user_id,
                'name'          => trim((string) $row->name),
                'hours'         => $this->hours($row->hours),
                'overtimeHours' => $this->hours($row->overtime_hours),
            ])
            ->values()
            ->all();
    }
}
