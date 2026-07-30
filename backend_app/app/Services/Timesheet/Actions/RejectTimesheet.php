<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Actions;

use App\Enums\TimesheetStatus;
use App\Models\Timesheet;
use App\Models\User;
use Throwable;

final readonly class RejectTimesheet
{
    public function __construct(
        private ReviewTimesheet $review,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(User $user, Timesheet $timesheet, ?string $comment): Timesheet
    {
        return $this->review->handle($user, $timesheet, TimesheetStatus::REJECTED, $comment);
    }
}
