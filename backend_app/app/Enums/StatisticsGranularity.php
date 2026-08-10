<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum StatisticsGranularity: string
{
    case DAY     = 'day';
    case WEEK    = 'week';
    case MONTH   = 'month';
    case QUARTER = 'quarter';

    public static function defaultForDays(int $days): self
    {
        return match (true) {
            $days <= 31  => self::DAY,
            $days <= 180 => self::WEEK,
            default      => self::MONTH,
        };
    }

    public function bucketStart(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::DAY     => $date->startOfDay(),
            self::WEEK    => $date->startOfWeek(CarbonInterface::MONDAY),
            self::MONTH   => $date->startOfMonth(),
            self::QUARTER => $date->startOfQuarter(),
        };
    }

    public function nextBucket(CarbonImmutable $bucketStart): CarbonImmutable
    {
        return match ($this) {
            self::DAY     => $bucketStart->addDay(),
            self::WEEK    => $bucketStart->addWeek(),
            self::MONTH   => $bucketStart->addMonth(),
            self::QUARTER => $bucketStart->addQuarter(),
        };
    }
}
