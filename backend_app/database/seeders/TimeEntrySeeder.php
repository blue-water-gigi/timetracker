<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Timesheet;
use App\Services\Timesheet\Data\CreateTimeEntryData;
use App\Services\Timesheet\TimesheetService;
use Illuminate\Database\Seeder;

class TimeEntrySeeder extends Seeder
{
    public function __construct(
        private readonly TimesheetService $timesheetService,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Timesheet::query()->each(
            function (Timesheet $timesheet): void {
                foreach (range(0, 1) as $dayOffset) {
                    $this->timesheetService->addEntry(
                        $timesheet,
                        CreateTimeEntryData::fromValidated([
                            'work_date'   => $timesheet->period_start->addDays($dayOffset)->toDateString(),
                            'description' => fake()->sentence(),
                            'hours'       => number_format(fake()->randomFloat(2, 1, 12), 2, '.', ''),
                            'is_overtime' => $dayOffset === 1,
                        ]),
                    );
                }
            },
        );
    }
}
