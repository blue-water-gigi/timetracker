<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Actions;

use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Services\Timesheet\Data\UpdateTimeEntryData;
use App\Services\Timesheet\TimesheetGuard;
use App\Services\Timesheet\TimesheetLock;
use Carbon\CarbonImmutable;
use DB;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

final readonly class UpdateTimeEntry
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private TimesheetLock $lock,
        private TimesheetGuard $guard,
    ) {}

    /**
     * @throws Throwable
     * @throws TransactionErrorException
     */
    public function handle(Timesheet $timesheet, TimeEntry $entry, UpdateTimeEntryData $data): TimeEntry
    {
        try {
            return DB::transaction(function () use ($timesheet, $data, $entry): TimeEntry {
                $lockedTimesheet = $this->lock->lockTimesheet($timesheet);
                $lockedEntry = $this->lock->lockEntry($entry, $lockedTimesheet);

                $this->guard->ensureEditable($lockedTimesheet);

                $workDate = $data->workDate ?? CarbonImmutable::instance($lockedEntry->work_date);
                $this->guard->ensureWorkDateInsidePeriod($lockedTimesheet, $workDate);

                if ($data->hasHours && $data->hours !== null) {
                    $this->guard->ensureValidHours($data->hours);
                }

                $lockedEntry->forceFill($data->toArray());

                if ($lockedEntry->isDirty()) {
                    $lockedEntry->saveOrFail();
                }

                return $lockedEntry;
            });
        } catch (DomainException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error updating time entry: '.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
