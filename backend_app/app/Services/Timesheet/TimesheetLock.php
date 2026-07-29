<?php

namespace App\Services\Timesheet;

use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TimesheetLock
{
    /**
     * @param Timesheet $timesheet
     * @return Timesheet
     * @throws ModelNotFoundException
     */
    public function lockTimesheet(Timesheet $timesheet): Timesheet
    {
        return Timesheet::query()
            ->whereKey($timesheet->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }


    /**
     * @param TimeEntry $entry
     * @param Timesheet $locked
     * @return TimeEntry
     * @throws ModelNotFoundException
     */
    public function lockEntry(TimeEntry $entry, Timesheet $locked): TimeEntry
    {
        /** @var TimeEntry */
        return $locked->entries()
            ->whereKey($entry->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
