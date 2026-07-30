<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class InvalidTimeEntryHoursException extends TimesheetValidationException
{
    public static function make(): self
    {
        return new self(
            'The hours must be between 0 and 24 and contain at most two decimal places.',
            'hours',
        );
    }

    public function errorCode(): string
    {
        return 'invalid_time_entry_hours';
    }
}
