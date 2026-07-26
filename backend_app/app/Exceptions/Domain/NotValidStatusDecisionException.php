<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\TimesheetStatus;

class NotValidStatusDecisionException extends TimesheetStateConflict
{
    public static function make(TimesheetStatus $currentStatus, array $allowedStatuses): self
    {
        return new self(
            "Decision must be 'approved' or 'rejected'.",
            $currentStatus,
            $allowedStatuses);
    }

    public function errorCode(): string
    {
        return 'timesheet_status_decision_not_valid';
    }
}
