<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProjectMember;
use App\Services\Timesheet\Data\TimesheetPeriodData;
use App\Services\Timesheet\TimesheetService;
use Illuminate\Database\Seeder;

class TimesheetSeeder extends Seeder
{
    public function __construct(
        private readonly TimesheetService $timesheetService,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProjectMember::query()
            ->where('active', true)
            ->with(['project', 'user'])
            ->each(
                function (ProjectMember $membership): void {
                    $periodStart = today()->startOfWeek();

                    $this->timesheetService->create(
                        $membership->project,
                        $membership->user,
                        TimesheetPeriodData::fromValidated([
                            'period_start' => $periodStart->toDateString(),
                            'period_end'   => $periodStart->copy()->endOfWeek()->toDateString(),
                        ]),
                    );
                },
            );
    }
}
