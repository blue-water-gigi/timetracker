<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::factory()->create([
            'name' => 'Google LLC',
            'slug' => 'google-llc',
        ]);

        Organization::factory()->create([
            'name' => 'Yandex LLC',
            'slug' => 'yandex-llc',
        ]);

        Organization::factory(10)->create();
    }
}
