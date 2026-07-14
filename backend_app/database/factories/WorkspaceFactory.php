<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workspace> */
class WorkspaceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->companySuffix(),
            'slug' => fake()->unique()->slug(),
            'join_code_hash' => Workspace::hashJoinCode(Workspace::generateJoinCode()),
            'active' => true,
        ];
    }

    public function withJoinCode(string $joinCode): static
    {
        return $this->state(fn (): array => [
            'join_code_hash' => Workspace::hashJoinCode($joinCode),
        ]);
    }
}
