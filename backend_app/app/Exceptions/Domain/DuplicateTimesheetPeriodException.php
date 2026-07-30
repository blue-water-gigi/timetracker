<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Throwable;

class DuplicateTimesheetPeriodException extends DomainException implements ShouldntReport
{
    private function __construct(?Throwable $previous)
    {
        parent::__construct(
            'A timesheet for this exact period already exists.',
            0,
            $previous,
        );
    }

    public static function make(?Throwable $previous = null): self
    {
        return new self($previous);
    }

    public function errorCode(): string
    {
        return 'duplicate_timesheet_period';
    }
}
