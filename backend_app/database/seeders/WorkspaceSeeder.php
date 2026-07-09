<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Workspace::factory()->create([
            'name' => 'Duckies LSC',
            'slug' => 'duckies',
        ]);

        Workspace::factory()->create([
            'name' => 'Google LSC',
            'slug' => 'google',
        ]);
    }
}
