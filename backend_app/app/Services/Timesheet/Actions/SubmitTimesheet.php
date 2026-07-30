<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Actions;

use App\Enums\TimesheetStatus;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Timesheet;
use App\Services\Timesheet\TimesheetGuard;
use App\Services\Timesheet\TimesheetLock;
use DB;
use Illuminate\Support\Carbon;
use Throwable;

final readonly class SubmitTimesheet
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
     */
    public function handle(Timesheet $timesheet): Timesheet
    {
        try {
            return DB::transaction(function () use ($timesheet): Timesheet {
                $locked = $this->lock->lockTimesheet($timesheet);
                $this->guard->ensureCanSubmit($locked);

                $locked->forceFill([
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                    'review_comment' => null,
                    'status' => TimesheetStatus::SUBMITTED,
                    'submitted_at' => Carbon::now(),
                ])->saveOrFail();

                return $locked;
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error submitting timesheet: '.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
