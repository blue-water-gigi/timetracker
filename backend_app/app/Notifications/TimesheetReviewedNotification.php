<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TimesheetReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $snapshot) {}

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
        return $this->snapshot;
    }

    public function databaseType(object $notifiable): string
    {
        return 'timesheet.reviewed';
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->snapshot;
    }
}
