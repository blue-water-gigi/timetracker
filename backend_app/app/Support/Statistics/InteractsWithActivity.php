<?php

declare(strict_types=1);

namespace App\Support\Statistics;

use Illuminate\Support\Collection;

trait InteractsWithActivity
{
    use InteractsWithHours;

    /**
     * Map daily activity for Eloquent query
     *
     * @return array<int, array<string, float|string>>
     */
    public function activity(StatisticsPeriod $period, iterable $rows): array
    {
        $activity = [];

        for ($date = $period->from; $date->lte($period->to); $date = $date->addDay()) {
            $key = $date->toDateString();

            $row = $rows instanceof Collection ? $rows->get($key) : $rows[$key];

            $activity[] = [
                'date'          => $key,
                'hours'         => $this->hours(data_get($row, 'hours')),
                'overtimeHours' => $this->hours(data_get($row, 'overtime_hours')),
            ];
        }

        return $activity;
    }
}
