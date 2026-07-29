<?php

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


    protected function errorCode(): string
    {
        return 'invalid_time_entry_hours';
    }
}
