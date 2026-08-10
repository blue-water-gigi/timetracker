<?php

declare(strict_types=1);

namespace App\Support\Statistics;

trait InteractsWithHours
{
    /**
     * Convert hours to float with certain precision
     */
    public function hours(int|float|null|string $value, int $precision = 2): float
    {
        return round((float) ($value ?? 0), $precision);
    }
}
