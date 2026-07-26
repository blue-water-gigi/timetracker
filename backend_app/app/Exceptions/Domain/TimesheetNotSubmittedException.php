<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\TimesheetStatus;

class TimesheetNotSubmittedException extends TimesheetStateConflict
{
    public static function make(TimesheetStatus $currentStatus, array $allowedStatuses): self
    {
        return new self(
            'Cannot approve or reject timesheet: it must be submitted first.',
            $currentStatus,
            $allowedStatuses);
    }

    public function errorCode(): string
    {
        return 'timesheet_not_submitted';
    }
}
