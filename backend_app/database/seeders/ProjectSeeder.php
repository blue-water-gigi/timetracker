<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Workspace::query()->each(
            fn (Workspace $workspace) => Project::factory()
                ->count(2)
                ->for($workspace)
                ->create(),
        );
    }
}
