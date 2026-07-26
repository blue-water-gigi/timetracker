<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

abstract class TimesheetPeriodConflict extends DomainException implements ShouldntReport
{
    protected function __construct(
        string $message,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd)
    {
        parent::__construct($message);
    }

    abstract public function errorCode(): string;
}
