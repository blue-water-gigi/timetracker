<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Timesheet> */
class TimesheetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => fn (array $attributes): int => Project::query()
                ->findOrFail($attributes['project_id'])
                ->workspace_id,
            'user_id' => User::factory(),
            'period_start' => today()->startOfWeek(),
            'period_end' => today()->endOfWeek(),
            'status' => TimesheetStatus::DRAFT,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => TimesheetStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
    }
}
