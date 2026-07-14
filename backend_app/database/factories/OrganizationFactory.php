<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->administrator(),
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
        ];
    }
}
