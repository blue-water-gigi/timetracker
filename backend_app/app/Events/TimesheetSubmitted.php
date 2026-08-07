<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimesheetSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $timesheetId,
        public int $workspaceId,
        public int $projectId,
        public int $authorId,
        public string $submittedAt,
    ) {}
}
