<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;

final class TimeEntryOutsideTimesheetPeriodException extends TimesheetPeriodConflict
{
    public static function make(CarbonInterface $periodStart, CarbonInterface $periodEnd): self
    {
        return new self('The work date must be within the timesheet period.', $periodStart, $periodEnd);
    }

    public function errorCode(): string
    {
        return 'time_entry_outside_timesheet_period';
    }
}
