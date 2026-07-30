<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Actions;

use App\Exceptions\Domain\DuplicateTimesheetPeriodException;
use App\Exceptions\Domain\TimesheetPeriodContainsEntriesException;
use App\Models\Timesheet;
use App\Services\Timesheet\Data\ChangeTimesheetPeriodData;
use App\Services\Timesheet\TimesheetGuard;
use App\Services\Timesheet\TimesheetLock;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

final readonly class ChangeTimesheetPeriod
{
    public function __construct(
        private TimesheetLock $lock,
        private TimesheetGuard $guard,
    ) {}

    /**
     * @throws Throwable
     * @throws DuplicateTimesheetPeriodException
     */
    public function handle(Timesheet $timesheet, ChangeTimesheetPeriodData $data): Timesheet
    {
        try {
            return DB::transaction(function () use ($timesheet, $data): Timesheet {
                $locked = $this->lock->lockTimesheet($timesheet);
                $this->guard->ensureEditable($locked);

                if ($data->isEmpty()) {
                    return $locked;
                }

                $period = $data->resolve($locked);

                $hasOutOfRangeEntries = $timesheet->entries()
                    ->where(function (Builder $query) use ($period): void {
                        $query
                            ->whereDate('work_date', '<', $period->periodStart->toDateString())
                            ->orWhereDate('work_date', '>', $period->periodEnd->toDateString());
                    })
                    ->exists();

                if ($hasOutOfRangeEntries) {
                    throw TimesheetPeriodContainsEntriesException::make(
                        $period->periodStart,
                        $period->periodEnd,
                        $data->errorField()
                    );
                }

                $locked->forceFill($data->changes($period));

                if ($locked->isDirty()) {
                    $locked->saveOrFail();
                }

                return $locked;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw DuplicateTimesheetPeriodException::make($e);
        }
    }
}
