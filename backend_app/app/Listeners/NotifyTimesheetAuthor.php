<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TimesheetReviewed;
use App\Models\User;
use App\Notifications\TimesheetReviewedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;
use Throwable;

class NotifyTimesheetAuthor implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;

    public ?string $queue = 'notifications';

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public int $maxExceptions = 3;

    public function __construct(private readonly LoggerInterface $logger) {}

    public function handle(TimesheetReviewed $event): void
    {
        $author = User::find($event->authorId);

        if (! $author) {
            $this->logger->error('Author not found.', [
                'author_id' => $event->authorId,
            ]);

            return;
        }

        $author->notifyNow(new TimesheetReviewedNotification($event->toSnapshot()));
    }

    /**
     * Calculate the number of seconds to wait before retrying the queued listener.
     *
     * @return list<int>
     */
    public function backoff(TimesheetReviewed $event): array
    {
        return [5, 15, 60];
    }

    public function failed(TimesheetReviewed $event, Throwable $th): void
    {
        $this->logger->error('Failed to send notification to timesheet author after all retries.', [
            'timesheet_id' => $event->timesheetId,
            'workspace_id' => $event->workspaceId,
            'exception'    => $th,
        ]);
    }

    /**
     * Get the unique ID for the listener.
     */
    public function uniqueId(TimesheetReviewed $event): string
    {
        return "timesheet:{$event->timesheetId}:reviewed:{$event->reviewedAt}:{$event->decision}";
    }
}
