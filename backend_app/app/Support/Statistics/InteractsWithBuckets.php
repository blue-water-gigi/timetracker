<?php

declare(strict_types=1);

namespace App\Support\Statistics;

use App\Enums\StatisticsGranularity;

trait InteractsWithBuckets
{
    /**
     * For pgsql only.
     *
     * Match date_trunc()::date case invariant to certain granularity
     */
    public function matchDateTruncBucket(StatisticsGranularity $granularity): string
    {
        // no default cuz $granularity comes from Request and validated to be in enum cases
        // 'e' is time_entries
        return match ($granularity) {
            StatisticsGranularity::DAY     => "date_trunc('day', e.work_date)::date",
            StatisticsGranularity::WEEK    => "date_trunc('week', e.work_date)::date",
            StatisticsGranularity::MONTH   => "date_trunc('month', e.work_date)::date",
            StatisticsGranularity::QUARTER => "date_trunc('quarter', e.work_date)::date",
        };
    }
}
