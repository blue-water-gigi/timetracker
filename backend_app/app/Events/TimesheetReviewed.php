<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimesheetReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $timesheetId,
        public int $workspaceId,
        public int $projectId,
        public int $authorId,
        public int $reviewerId,
        public string $decision,
        public string $reviewedAt,
        public ?string $reviewComment,
    ) {}

    /**
     * @return array{
     *  timesheetId: int,
     *  workspaceId: int,
     *  projectId: int,
     *  reviewerId: int,
     *  decision: string,
     *  reviewedAt: string,
     *  reviewComment: ?string
     * }
     */
    public function toSnapshot(): array
    {
        return [
            'timesheetId'   => $this->timesheetId,
            'workspaceId'   => $this->workspaceId,
            'projectId'     => $this->projectId,
            'reviewerId'    => $this->reviewerId,
            'decision'      => $this->decision,
            'reviewedAt'    => $this->reviewedAt,
            'reviewComment' => $this->reviewComment ?? null,
        ];
    }
}
