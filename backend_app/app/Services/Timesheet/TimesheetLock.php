<?php

declare(strict_types=1);

namespace App\Services\Timesheet;

use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TimesheetLock
{
    /**
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
