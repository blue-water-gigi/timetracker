<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;

class TimesheetPeriodContainsEntriesException extends TimesheetPeriodConflict
{
    public static function make(
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        string $field,
    ): TimesheetPeriodConflict {
        return new self(
            'Invalid time entry period.',
            $periodStart,
            $periodEnd,
            $field,
        );
    }

    public function errorCode(): string
    {
        return 'invalid_time_entry_period';
    }
}
