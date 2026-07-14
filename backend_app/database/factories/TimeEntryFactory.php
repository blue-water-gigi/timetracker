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
            'work_date' => today(),
            'description' => fake()->optional()->sentence(),
            'hours' => fake()->randomFloat(2, 1, 12),
            'is_overtime' => false,
        ];
    }
}
