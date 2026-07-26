<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\TimesheetStatus;

class TimesheetAlreadyProcessedException extends TimesheetStateConflict
{
    public static function make(TimesheetStatus $currentStatus, array $allowedStatuses): self
    {
        return new self(
            "Timesheet already processed as {$currentStatus->value}.",
            $currentStatus,
            $allowedStatuses);
    }

    public function errorCode(): string
    {
        return 'timesheet_already_processed';
    }
}
