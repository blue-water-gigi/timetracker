<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TimesheetSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Timesheet $timesheet) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'timesheetId' => $this->timesheet->id,
            'workspaceId' => $this->timesheet->workspace_id,
            'projectId'   => $this->timesheet->project_id,
            'authorId'    => $this->timesheet->user_id,
            'submittedAt' => $this->timesheet->submitted_at?->toDateTimeString(),
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'timesheet.submitted';
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'timesheetId' => $this->timesheet->id,
            'workspaceId' => $this->timesheet->workspace_id,
            'projectId'   => $this->timesheet->project_id,
            'authorId'    => $this->timesheet->user_id,
            'submittedAt' => $this->timesheet->submitted_at?->toDateTimeString(),
        ];
    }
}
