<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Services\Timesheet\TimesheetService;
use Illuminate\Database\Seeder;

class TimesheetWorkflowSeeder extends Seeder
{
    public function __construct(
        private readonly TimesheetService $timesheetService,
    ) {}

    public function run(): void
    {
        Timesheet::query()
            ->with(['project', 'user', 'workspace.organization.owner'])
            ->each(function (Timesheet $timesheet): void {
                $membership = ProjectMember::query()
                    ->whereBelongsTo($timesheet->project)
                    ->whereBelongsTo($timesheet->user)
                    ->firstOrFail();

                $reviewer = $timesheet->workspace->organization->owner;

                switch ($membership->project_role) {
                    case ProjectRole::PARTICIPANT:
                        break;

                    case ProjectRole::SENIOR:
                        $this->timesheetService->submit($timesheet);
                        break;

                    case ProjectRole::MANAGER:
                        $this->timesheetService->submit($timesheet);
                        $this->timesheetService->approve($reviewer, $timesheet, 'Seeded approval.');
                        break;

                    case ProjectRole::PROJECT_LEAD:
                        $this->timesheetService->submit($timesheet);
                        $this->timesheetService->reject($reviewer, $timesheet, 'Seeded rejection.');
                        break;
                }
            });
    }
}
