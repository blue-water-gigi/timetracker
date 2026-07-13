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
            'name' => 'Department 1',
            'slug' => 'juries',
            'organization_id' => 1,
            'join_code' => '12343242134',
        ]);

        Workspace::factory()->create([
            'name' => 'Department 2',
            'slug' => 'test',
            'organization_id' => 1,
            'join_code' => '123asdfasdf234dsfa',
        ]);

        Workspace::factory()->create([
            'name' => 'Department 1',
            'slug' => 'test2',
            'organization_id' => 2,
            'join_code' => '12343df24dsafsdaf2134',
        ]);

        Workspace::factory(30)->create();
    }
}
