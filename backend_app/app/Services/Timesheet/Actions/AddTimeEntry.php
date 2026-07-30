<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Actions;

use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Services\Timesheet\Data\CreateTimeEntryData;
use App\Services\Timesheet\TimesheetGuard;
use App\Services\Timesheet\TimesheetLock;
use DB;
use Throwable;

final readonly class AddTimeEntry
{
    public function __construct(
        private TimesheetLock $lock,
        private TimesheetGuard $guard,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Timesheet $timesheet, CreateTimeEntryData $data): TimeEntry
    {
        try {
            return DB::transaction(function () use ($timesheet, $data) {
                $locked = $this->lock->lockTimesheet($timesheet);
                $this->guard->ensureEditable($locked);

                $this->guard->ensureWorkDateInsidePeriod($locked, $data->workDate);
                $this->guard->ensureValidHours($data->hours);

                $entry = $locked->entries()
                    ->make($data->toArray());

                $entry->saveOrFail();

                return $entry;
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error creating time entry: '.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
