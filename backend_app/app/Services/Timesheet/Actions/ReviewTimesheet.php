<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Actions;

use App\Enums\TimesheetStatus;
use App\Events\TimesheetReviewed;
use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Timesheet\TimesheetGuard;
use App\Services\Timesheet\TimesheetLock;
use DB;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @internal for aprove or reject
 */
final readonly class ReviewTimesheet
{
    public function __construct(
        private TimesheetGuard $guard,
        private TimesheetLock $lock,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(
        User $reviewer,
        Timesheet $timesheet,
        TimesheetStatus $decision,
        ?string $comment
    ): Timesheet {
        try {
            return DB::transaction(function () use ($reviewer, $timesheet, $decision, $comment): Timesheet {
                $locked = $this->lock->lockTimesheet($timesheet);
                $this->guard->ensureSubmitted($locked);
                $this->guard->ensureReviewComment(
                    $comment,
                    $decision === TimesheetStatus::REJECTED
                );

                $locked->forceFill([
                    'reviewed_by_user_id' => $reviewer->getKey(),
                    'reviewed_at' => Carbon::now(),
                    'review_comment' => $comment,
                    'status' => $decision,
                ])->saveOrFail();

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: $locked->workspace_id,
                    reason: 'timesheet_reviewed'
                );

                TimesheetReviewed::dispatch(
                    timesheetId: (int) $locked->getKey(),
                    workspaceId: (int) $locked->workspace_id,
                    projectId: (int) $locked->project_id,
                    authorId: (int) $locked->user_id,
                    reviewerId: (int) $locked->reviewed_by_user_id,
                    decision: $decision->value,
                    reviewedAt: (string) $locked->reviewed_at,
                    reviewComment: $locked->review_comment
                );

                return $locked;
            });
        } catch (DomainException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error reviewing timesheet: '.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
