<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\TimesheetStatus;
use App\Events\TimesheetSubmitted;
use App\Models\Timesheet;
use App\Notifications\TimesheetSubmittedNotification;
use App\Queries\EloquentFindTimesheetApprovers;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;
use Psr\Log\LoggerInterface;
use Throwable;

class NotifyTimesheetApprovers implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;

    public ?string $queue = 'notifications';

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public int $maxExceptions = 3;

    public function __construct(
        private readonly EloquentFindTimesheetApprovers $findTimesheetApprovers,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(TimesheetSubmitted $event): void
    {
        $timesheet = Timesheet::query()->find($event->timesheetId);

        if (! $timesheet
            || $timesheet->workspace_id !== $event->workspaceId
            || $timesheet->project_id   !== $event->projectId) {
            $this->logger->error('Timesheet not found or comes from other tenant.', [
                'timesheet_id' => $event->timesheetId,
                'workspace_id' => $event->workspaceId,
                'project_id'   => $event->projectId,
            ]);

            return;
        }

        if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
            $this->logger->info('Stale submitted-timesheet notification skipped.', [
                'timesheet_id' => $event->timesheetId,
                'status'       => $timesheet->status->value,
            ]);

            return;
        }

        if ($timesheet->submitted_at?->toDateTimeString() !== $event->submittedAt) {
            $this->logger->info('Stale submitted-timesheet notification skipped.', [
                'in_event' => [
                    'timesheet_id' => $event->timesheetId,
                    'submitted_at' => $event->submittedAt,
                ],
                'in_timesheet' => [
                    'timesheet_id' => $timesheet->id,
                    'submitted_at' => $timesheet->submitted_at,
                ],
            ]);

            return;
        }

        $approvers = $this->findTimesheetApprovers->find(
            authorId: $event->authorId,
            projectId: $event->projectId,
        );

        Notification::sendNow($approvers, new TimesheetSubmittedNotification($timesheet));
    }

    /**
     * Calculate the number of seconds to wait before retrying the queued listener.
     *
     * @return list<int>
     */
    public function backoff(TimesheetSubmitted $event): array
    {
        return [5, 15, 60];
    }

    public function failed(TimesheetSubmitted $event, Throwable $th): void
    {
        $this->logger->error('Failed to send notifications to timesheet approvers after all retries.', [
            'timesheet_id' => $event->timesheetId,
            'workspace_id' => $event->workspaceId,
            'exception'    => $th,
        ]);
    }

    /**
     * Get the unique ID for the listener.
     */
    public function uniqueId(TimesheetSubmitted $event): string
    {
        return "timesheet:{$event->timesheetId}:submitted:{$event->submittedAt}";
    }
}
