<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Debug\ShouldntReport;

abstract class TimesheetPeriodConflict extends TimesheetValidationException implements ShouldntReport
{
    protected function __construct(
        string $message,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
        string $field,
    ) {
        parent::__construct($message, $field);
    }

    abstract public function errorCode(): string;
}
