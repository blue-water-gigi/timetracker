<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Data;

use App\Models\Timesheet;
use Carbon\CarbonImmutable;

readonly class ChangeTimesheetPeriodData
{
    /**
     * Create a new class instance.
     */
    private function __construct(
        public ?CarbonImmutable $periodStart,
        public ?CarbonImmutable $periodEnd,
        public bool $hasPeriodStart,
        public bool $hasPeriodEnd,
    ) {}

    /**
     * @param  array{period_start?:string, period_end?:string}  $attributes
     */
    public static function fromValidated(array $attributes): self
    {
        $hasPeriodStart = array_key_exists('period_start', $attributes);
        $hasPeriodEnd = array_key_exists('period_end', $attributes);

        return new self(
            $hasPeriodStart ? $attributes['period_start'] : null,
            $hasPeriodEnd ? $attributes['period_end'] : null,
            $hasPeriodStart,
            $hasPeriodEnd,
        );
    }

    public function isEmpty(): bool
    {
        return ! $this->hasPeriodStart && ! $this->hasPeriodEnd;
    }

    public function resolve(Timesheet $lockedTimesheet): TimesheetPeriodData
    {
        $current = TimesheetPeriodData::fromTimesheet($lockedTimesheet);

        return TimesheetPeriodData::make(
            $this->periodStart ?? $current->periodStart,
            $this->periodEnd ?? $current->periodEnd,
            $this->errorField()
        );
    }

    /**
     * @return array{period_start:string, period_end:string}
     */
    public function changes(TimesheetPeriodData $resolved): array
    {
        $changes = [];

        if ($this->hasPeriodStart) {
            $changes['period_start'] = $resolved->periodStart->toDateString();
        }

        if ($this->hasPeriodEnd) {
            $changes['period_end'] = $resolved->periodEnd->toDateString();
        }

        return $changes;
    }

    public function errorField(): string
    {
        return $this->hasPeriodEnd ? 'period_end' : 'period_start';
    }
}
