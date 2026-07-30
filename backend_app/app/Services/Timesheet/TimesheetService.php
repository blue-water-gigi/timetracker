<?php

declare(strict_types=1);

namespace App\Services\Timesheet;

use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Timesheet\Actions\AddTimeEntry;
use App\Services\Timesheet\Actions\ApproveTimesheet;
use App\Services\Timesheet\Actions\ChangeTimesheetPeriod;
use App\Services\Timesheet\Actions\CreateTimesheet;
use App\Services\Timesheet\Actions\RejectTimesheet;
use App\Services\Timesheet\Actions\RemoveTimeEntry;
use App\Services\Timesheet\Actions\SubmitTimesheet;
use App\Services\Timesheet\Actions\UpdateTimeEntry;
use App\Services\Timesheet\Data\ChangeTimesheetPeriodData;
use App\Services\Timesheet\Data\CreateTimeEntryData;
use App\Services\Timesheet\Data\TimesheetPeriodData;
use App\Services\Timesheet\Data\UpdateTimeEntryData;
use Throwable;

readonly class TimesheetService
{
    public function __construct(
        private AddTimeEntry $addTimeEntry,
        private ApproveTimesheet $approveTimesheet,
        private ChangeTimesheetPeriod $changeTimesheetPeriod,
        private CreateTimesheet $createTimesheet,
        private RejectTimesheet $rejectTimesheet,
        private RemoveTimeEntry $removeTimeEntry,
        private SubmitTimesheet $submitTimesheet,
        private UpdateTimeEntry $updateTimeEntry,
    ) {}

    /**
     * @throws TransactionErrorException
     */
    public function create(Project $project, User $author, TimesheetPeriodData $data): Timesheet
    {
        return $this->createTimesheet->handle($project, $author, $data);
    }

    /**
     * @throws Throwable
     */
    public function changePeriod(Timesheet $timesheet, ChangeTimesheetPeriodData $data): Timesheet
    {
        return $this->changeTimesheetPeriod->handle($timesheet, $data);
    }

    /**
     * @throws Throwable
     */
    public function addEntry(Timesheet $timesheet, CreateTimeEntryData $data): TimeEntry
    {
        return $this->addTimeEntry->handle($timesheet, $data);
    }

    /**
     * @throws TransactionErrorException
     * @throws Throwable
     */
    public function updateEntry(Timesheet $timesheet, TimeEntry $entry, UpdateTimeEntryData $data): TimeEntry
    {
        return $this->updateTimeEntry->handle($timesheet, $entry, $data);
    }

    /**
     * @throws Throwable
     */
    public function removeEntry(Timesheet $timesheet, TimeEntry $entry): void
    {
        $this->removeTimeEntry->handle($timesheet, $entry);
    }

    /**
     * @throws Throwable
     */
    public function approve(User $user, Timesheet $timesheet, ?string $comment): Timesheet
    {
        return $this->approveTimesheet->handle($user, $timesheet, $comment);
    }

    /**
     * @throws Throwable
     */
    public function submit(Timesheet $timesheet): Timesheet
    {
        return $this->submitTimesheet->handle($timesheet);
    }

    /**
     * @throws Throwable
     */
    public function reject(User $user, Timesheet $timesheet, ?string $comment): Timesheet
    {
        return $this->rejectTimesheet->handle($user, $timesheet, $comment);
    }
}
