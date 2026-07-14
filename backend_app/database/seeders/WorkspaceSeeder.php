<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::query()->limit(2)->get();

        Workspace::factory()
            ->for($organizations->first())
            ->withJoinCode('development-workspace-one')
            ->create(['name' => 'Department 1', 'slug' => 'department-1']);

        Workspace::factory()
            ->for($organizations->first())
            ->withJoinCode('development-workspace-two')
            ->create(['name' => 'Department 2', 'slug' => 'department-2']);

        Workspace::factory()
            ->for($organizations->last())
            ->withJoinCode('development-workspace-three')
            ->create(['name' => 'Department 1', 'slug' => 'department-1']);

        Workspace::factory(30)->create();
    }
}
