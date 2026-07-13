<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::factory()->create([
            'name' => 'Google LLC',
            'slug' => 'google-llc',
            'tin' => '123456789',
            'metadata' => [
                'data' => 'some data',
            ],
        ]);

        Organization::factory()->create([
            'name' => 'Yandex LLC',
            'slug' => 'yandex-llc',
            'tin' => '574363205',
            'metadata' => [
                'data' => 'some data',
            ],
        ]);

        Organization::factory(10)->create();
    }
}
