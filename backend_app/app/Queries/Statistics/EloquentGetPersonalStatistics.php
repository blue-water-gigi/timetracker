<?php

declare(strict_types=1);

namespace App\Queries\Statistics;

use App\Contracts\Queries\Statistics\GetPersonalStatistics;
use App\Enums\TimesheetStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Statistics\InteractsWithBuckets;
use App\Support\Statistics\InteractsWithHours;
use App\Support\Statistics\StatisticsPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** PostgreSQL only */
readonly class EloquentGetPersonalStatistics implements GetPersonalStatistics
{
    use InteractsWithBuckets, InteractsWithHours;

    /**
     * @return array<string,mixed>
     */
    public function execute(Workspace $workspace, User $viewer, StatisticsPeriod $period): array
    {
        $summary = $this->summary($workspace, $viewer, $period);

        return [
            'period'        => [...$period->toArray(), 'status' => TimesheetStatus::APPROVED->value],
            'summary'       => $summary,
            'timeline'      => $this->timeline($workspace, $viewer, $period),
            'dailyActivity' => $this->dailyActivity($workspace, $viewer, $period),
            'projects'      => $this->projects($workspace, $viewer, $period, $summary['totalHours']),
        ];
    }

    /**
     * @return array{
     *     totalHours: float,
     *     previousHours: float,
     *     deltaHours: float,
     *     deltaPercent: float|null,
     *     overtimeHours: float,
     *     overtimeSharePercent: float,
     *     pendingHours: float
     * }
     */
    private function summary(Workspace $workspace, User $viewer, StatisticsPeriod $period): array
    {
        $approved     = TimesheetStatus::APPROVED->value;
        $submitted    = TimesheetStatus::SUBMITTED->value;
        $from         = $period->from->toDateString();
        $to           = $period->to->toDateString();
        $previousFrom = $period->previousFrom()->toDateString();
        $previousTo   = $period->previousTo()->toDateString();

        // get the row with total, previous, overtime, pending hours
        // COALESCE to get zero's instead of nulls
        $row = DB::table('time_entries AS e')
            ->join('timesheets AS t', 't.id', '=', 'e.timesheet_id')
            ->where('t.workspace_id', $workspace->getKey())
            ->where('t.user_id', $viewer->getKey())
            ->whereIn('t.status', [$approved, $submitted])
            ->whereBetween('e.work_date', [$previousFrom, $to])
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
            WHERE t.status = ? AND e.work_date BETWEEN ? AND ?), 0) AS total_hours', [
                $approved, $from, $to,
            ])
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
            WHERE t.status = ? AND e.work_date BETWEEN ? AND ?), 0) AS previous_hours', [
                $approved, $previousFrom, $previousTo,
            ])
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
            WHERE t.status = ? AND e.work_date BETWEEN ? AND ? AND e.is_overtime = TRUE), 0) AS overtime_hours', [
                $approved, $from, $to,
            ])
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (
            WHERE t.status = ? AND e.work_date BETWEEN ? AND ?), 0) AS pending_hours', [
                $submitted, $from, $to,
            ])
            ->first();

        // calculate delta
        $totalHours    = $this->hours(data_get($row, 'total_hours'));
        $previousHours = $this->hours(data_get($row, 'previous_hours'));
        $overtimeHours = $this->hours(data_get($row, 'overtime_hours'));
        $deltaHours    = $this->hours($totalHours - $previousHours);

        return [
            'totalHours'           => $totalHours,
            'previousHours'        => $previousHours,
            'deltaHours'           => $deltaHours,
            'deltaPercent'         => $previousHours > 0 ? round($deltaHours / $previousHours * 100, 2) : null,
            'overtimeHours'        => $overtimeHours,
            'overtimeSharePercent' => $totalHours > 0 ? round($overtimeHours / $totalHours * 100, 2) : 0.0,
            'pendingHours'         => $this->hours(data_get($row, 'pending_hours')),
        ];
    }

    private function approved(Workspace $workspace, User $viewer, StatisticsPeriod $period): Builder
    {
        $approved = TimesheetStatus::APPROVED->value;

        return DB::table('time_entries AS e')
            ->join('timesheets AS t', 't.id', '=', 'e.timesheet_id')
            ->where('t.workspace_id', $workspace->getKey())
            ->where('t.user_id', $viewer->getKey())
            ->where('t.status', $approved)
            ->whereBetween('e.work_date', [
                $period->from->toDateString(),
                $period->to->toDateString(),
            ]);
    }

    /**
     * @return array<int, array<string, float|string>>
     */
    private function timeline(Workspace $workspace, User $viewer, StatisticsPeriod $period): array
    {
        $granularityExpression = $this->matchDateTruncBucket($period->granularity);

        $rows = $this->approved($workspace, $viewer, $period)
            ->selectRaw("$granularityExpression AS bucket_start")
            ->selectRaw('SUM(e.hours) AS hours')
            ->selectRaw('COALESCE (SUM(e.hours) FILTER(WHERE e.is_overtime = TRUE), 0) AS overtime_hours')
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
     * @return array<int, array<string, float|string>>
     */
    private function dailyActivity(Workspace $workspace, User $viewer, StatisticsPeriod $period): array
    {
        $rows = $this->approved($workspace, $viewer, $period)
            ->select('e.work_date')
            ->selectRaw('COALESCE (SUM(e.hours), 0) AS hours')
            ->selectRaw('COALESCE (SUM(e.hours) FILTER (WHERE e.is_overtime = TRUE), 0) AS overtime_hours')
            ->groupByRaw('e.work_date')
            ->get()
            ->keyBy(
                fn (object $row): string => CarbonImmutable::parse(
                    (string) data_get($row, 'work_date')
                )->toDateString()
            );

        $activity = [];

        for ($date = $period->from; $date->lte($period->to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);

            $activity[] = [
                'date'          => $key,
                'hours'         => $this->hours(data_get($row, 'hours')),
                'overtimeHours' => $this->hours(data_get($row, 'overtime_hours')),
            ];
        }

        return $activity;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projects(Workspace $workspace, User $viewer, StatisticsPeriod $period, float $totalHours): array
    {
        return $this->approved($workspace, $viewer, $period)
            ->join('projects', 'projects.id', '=', 't.project_id')
            ->select(['projects.id AS project_id', 'projects.name AS project_name'])
            ->selectRaw('SUM(e.hours) AS hours')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('hours')
            ->orderBy('projects.id')
            ->get()
            ->map(fn (object $row): array => [
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
}
