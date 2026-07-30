<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

readonly class UpdateTimeEntryData implements Arrayable
{
    private function __construct(
        public ?CarbonImmutable $workDate,
        public ?string $description,
        public ?string $hours,
        public ?bool $isOvertime,
        public bool $hasWorkDate,
        public bool $hasDescription,
        public bool $hasHours,
        public bool $hasIsOvertime,
    ) {}

    /**
     * @param array{
     * work_date:string,
     * description?:string|null,
     * hours:numeric-string|int|float,
     * is_overtime?:bool} $attributes
     */
    public static function fromValidated(array $attributes): self
    {
        $hasWorkDate = array_key_exists('workDate', $attributes);
        $hasDescription = array_key_exists('description', $attributes);
        $hasHours = array_key_exists('hours', $attributes);
        $hasIsOvertime = array_key_exists('isOvertime', $attributes);

        return new self(
            $hasWorkDate ? CarbonImmutable::parse($attributes['workDate'])->startOfDay() : null,
            $hasDescription ? $attributes['description'] : null,
            $hasHours ? $attributes['hours'] : null,
            $hasIsOvertime ? $attributes['isOvertime'] : null,
            $hasWorkDate,
            $hasDescription,
            $hasHours,
            $hasIsOvertime
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $attributes = [];

        if ($this->hasWorkDate) {
            $attributes['workDate'] = $this->workDate->toDateString();
        }

        if ($this->hasDescription) {
            $attributes['description'] = $this->description;
        }

        if ($this->hasHours) {
            $attributes['hours'] = $this->hours;
        }

        if ($this->hasIsOvertime) {
            $attributes['isOvertime'] = $this->isOvertime;
        }

        return $attributes;
    }
}
