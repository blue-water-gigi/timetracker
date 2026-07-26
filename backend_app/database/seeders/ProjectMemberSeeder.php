<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Database\Seeder;

class ProjectMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::query()->each(function (Project $project): void {
            foreach (ProjectRole::cases() as $role) {
                ProjectMember::factory()
                    ->for($project)
                    ->withRole($role)
                    ->create();
            }
        });
    }
}
