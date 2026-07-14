<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by_user_id' => User::factory()->administrator(),
            'updated_by_user_id' => User::factory()->administrator(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->optional()->sentence(),
            'slug' => fake()->unique()->slug(),
            'active' => true,
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
        ];
    }
}
