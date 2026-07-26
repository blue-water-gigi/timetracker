<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProjectMember;
use App\Models\Timesheet;
use Illuminate\Database\Seeder;

class TimesheetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProjectMember::query()
            ->where('active', true)
            ->each(
                fn (ProjectMember $membership) => Timesheet::factory()->create([
                    'project_id' => $membership->project_id,
                    'user_id' => $membership->user_id,
                ]),
            );
    }
}
