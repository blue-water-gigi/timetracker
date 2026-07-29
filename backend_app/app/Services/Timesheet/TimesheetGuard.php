<?php

namespace App\Services\Timesheet;

use App\Enums\TimesheetStatus;
use App\Exceptions\Domain\InvalidTimeEntryHoursException;
use App\Exceptions\Domain\ReviewCommentRequiredException;
use App\Exceptions\Domain\ReviewCommentTooLongException;
use App\Exceptions\Domain\TimeEntryOutsideTimesheetPeriodException;
use App\Exceptions\Domain\TimesheetAlreadyProcessedException;
use App\Exceptions\Domain\TimesheetNotSubmittedException;
use App\Models\Timesheet;
use Carbon\CarbonInterface;

class TimesheetGuard
{
    public function ensureEditable(Timesheet $timesheet): void
    {
        if (!$timesheet->status->isEditable()) {
            throw TimesheetAlreadyProcessedException::make(
                $timesheet->status,
                TimesheetStatus::editable()
            );
        }
    }

    public function ensureCanSubmit(Timesheet $timesheet): void
    {
        if (!$timesheet->status->canSubmit()) {
            throw TimesheetAlreadyProcessedException::make(
                $timesheet->status,
                TimesheetStatus::editable()
            );
        }
    }

    public function ensureSubmitted(Timesheet $timesheet): void
    {
        if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
            throw TimesheetNotSubmittedException::make(
                $timesheet->status,
                [TimesheetStatus::SUBMITTED]
            );
        }
    }

    public function ensureWorkDateInsidePeriod(Timesheet $timesheet, CarbonInterface $workDate): void
    {
        if ($workDate->isBefore($timesheet->period_start) || $workDate->isAfter($timesheet->period_end)) {
            throw TimeEntryOutsideTimesheetPeriodException::make(
                $timesheet->period_start,
                $timesheet->period_end,
            );
        }
    }

    public function ensureValidHours($hours): void
    {
        $hasValidFormat = preg_match('/^\d+(?:\.\d{1,2})?$/D', $hours) === 1;

        if (!$hasValidFormat || (float)$hours > 24) {
            throw InvalidTimeEntryHoursException::make();
        }
    }

    public function ensureReviewComment(?string $comment, bool $required): void
    {
        if ($required && ($comment === null || trim($comment) === '')) {
            throw ReviewCommentRequiredException::make();
        }

        if ($comment !== null && mb_strlen($comment) > 500) {
            throw ReviewCommentTooLongException::make();
        }
    }
}

