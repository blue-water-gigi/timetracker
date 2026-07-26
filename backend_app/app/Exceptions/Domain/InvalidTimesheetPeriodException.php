<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;

class InvalidTimesheetPeriodException extends TimesheetPeriodConflict
{
    public static function make(CarbonInterface $periodStart, CarbonInterface $periodEnd): TimesheetPeriodConflict
    {
        return new self('Invalid timesheet period.', $periodStart, $periodEnd);
    }

    public function errorCode(): string
    {
        return 'invalid_timesheet_period';
    }
}
