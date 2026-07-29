<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;

class InvalidTimesheetPeriodException extends TimesheetPeriodConflict
{
    public static function make(
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        string          $field = 'period_end'
    ): TimesheetPeriodConflict
    {
        return new self(
            'The period start must not be after the period end.',
            $periodStart, $periodEnd,
            $field
        );
    }

    public function errorCode(): string
    {
        return 'invalid_timesheet_period';
    }
}
