<?php

namespace App\Services\Timesheet\Data;

use App\Exceptions\Domain\InvalidTimesheetPeriodException;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;

readonly class TimesheetPeriodData implements Arrayable
{
    private function __construct(
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd
    )
    {
    }

    /**
     * @param array{period_start:string, period_end:string} $attributes
     * @return self
     */
    public static function fromValidated(array $attributes): self
    {
        return self::make(
            CarbonImmutable::parse($attributes['period_start']),
            CarbonImmutable::parse($attributes['period_end'])
        );
    }

    public static function fromTimesheet(Timesheet $timesheet): self
    {
        return self::make(
            CarbonImmutable::instance($timesheet->period_start),
            CarbonImmutable::instance($timesheet->period_end)
        );
    }

    public static function make(
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        string          $errorField = 'period_end'
    ): self
    {
        if ($periodStart->isAfter($periodEnd)) {
            throw InvalidTimesheetPeriodException::make($periodStart, $periodEnd);
        }

        return new self($periodStart->startOfDay(), $periodEnd->endOfDay());
    }

    public function contains(CarbonInterface $date): bool
    {
        return !$date->isBefore($this->periodStart)
            && !$date->isAfter($this->periodEnd);
    }

    /**
     * @return array{period_start: string, period_end: string}
     */
    public function toArray(): array
    {
        return [
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
        ];
    }
}
