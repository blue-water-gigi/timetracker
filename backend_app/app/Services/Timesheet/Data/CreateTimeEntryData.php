<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

readonly class CreateTimeEntryData implements Arrayable
{
    /**
     * Create a new class instance.
     */
    private function __construct(
        public CarbonImmutable $workDate,
        public ?string $description,
        public string $hours,
        public bool $isOvertime,
    ) {}

    /**
     * @param array{
     *     work_date: string,
     *     description?: string|null,
     *     hours: numeric-string|int|float,
     *     is_overtime?: bool
     * } $attributes
     */
    public static function fromValidated(array $attributes): self
    {
        return new self(
            CarbonImmutable::parse($attributes['work_date'])->startOfDay(),
            $attributes['description'],
            $attributes['hours'],
            (bool) ($attributes['is_overtime'] ?? false),
        );
    }

    /**
     * @return array{
     *      work_date: string,
     *      description?: string|null,
     *      hours: numeric-string|int|float,
     *      is_overtime?: bool
     *  }
     */
    public function toArray(): array
    {
        return [
            'work_date' => $this->workDate->toDateString(),
            'description' => $this->description,
            'hours' => $this->hours,
            'is_overtime' => $this->isOvertime,
        ];
    }
}
