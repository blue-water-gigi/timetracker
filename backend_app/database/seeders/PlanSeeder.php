<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use JsonException;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        Plan::factory()->create([
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Free plan perfect for testing',
            //            'features' => json_encode([
            //                'tba',
            //            ], JSON_THROW_ON_ERROR),
            'is_active' => true,
        ]);

        Plan::factory()->create([
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Basic plan',
            //            'features' => json_encode([
            //                'tba',
            //            ], JSON_THROW_ON_ERROR),
            'is_active' => true,
        ]);

    }
}
