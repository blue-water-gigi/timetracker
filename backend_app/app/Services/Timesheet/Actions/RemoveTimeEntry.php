<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Actions;

use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Services\Timesheet\TimesheetGuard;
use App\Services\Timesheet\TimesheetLock;
use DB;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

final readonly class RemoveTimeEntry
{
    public function __construct(
        private TimesheetGuard $guard,
        private TimesheetLock $lock,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Timesheet $timesheet, TimeEntry $entry): void
    {
        try {
            DB::transaction(function () use ($timesheet, $entry) {
                $lockedTimesheet = $this->lock->lockTimesheet($timesheet);
                $lockedEntry = $this->lock->lockEntry($entry, $lockedTimesheet);

                $this->guard->ensureEditable($lockedTimesheet);

                $lockedEntry->deleteOrFail();
            });
        } catch (DomainException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error deleting time entry: '.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
