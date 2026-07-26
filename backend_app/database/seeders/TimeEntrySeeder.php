<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Database\Seeder;

class TimeEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Timesheet::query()->each(
            fn (Timesheet $timesheet) => TimeEntry::factory()
                ->count(2)
                ->for($timesheet)
                ->create(),
        );
    }
}
