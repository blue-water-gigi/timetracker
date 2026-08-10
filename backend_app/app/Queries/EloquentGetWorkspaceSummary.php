<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Queries\GetWorkspaceSummary;
use App\Enums\TimesheetStatus;
use App\Models\User;
use App\Models\Workspace;

class EloquentGetWorkspaceSummary implements GetWorkspaceSummary
{
    /**
     * {@inheritDoc}
     */
    public function execute(Workspace $workspace, User $viewer): array
    {
        return [
            'workspace_id'           => $workspace->getKey(),
            'projects_count'         => $workspace->projects()->count(),
            'members_count'          => $workspace->memberships()->count(),
            'valid_timesheets_count' => $workspace->timesheets()
                ->whereBelongsTo($viewer, 'user')
                ->whereIn('status', TimesheetStatus::validTimesheets())
                ->count(),
        ];
    }
}
