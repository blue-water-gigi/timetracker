<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TimeEntry> */
class TimeEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'timesheet_id' => Timesheet::factory(),
            'work_date' => fn (array $attributes) => Timesheet::query()
                ->findOrFail($attributes['timesheet_id'])->period_start,
            'description' => fake()->optional()->sentence(),
            'hours' => number_format(fake()->randomFloat(2, 1, 12), 2, '.', ''),
            'is_overtime' => false,
        ];
    }

    public function overtime(): static
    {
        return $this->state(fn (): array => [
            'is_overtime' => true,
        ]);
    }
}
