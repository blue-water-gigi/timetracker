<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\SystemRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentFindTimesheetApprovers
{
    /** @return Collection<int, User> */
    public function find(int $authorId, int $projectId): Collection
    {
        $authorApprovalRank = ProjectMember::query()
            ->select('approval_rank')
            ->where('project_id', $projectId)
            ->where('user_id', $authorId)
            ->where('active', true)
            ->limit(1);

        $projectWorkspaceId = Project::query()
            ->select('workspace_id')
            ->whereKey($projectId)
            ->limit(1);

        return User::query()
            ->select((new User)->qualifyColumn('id'))
            ->whereKeyNot($authorId)
            ->where(function (Builder $recipients) use (
                $authorApprovalRank,
                $projectId,
                $projectWorkspaceId
            ): void {
                $recipients
                    ->where(function (Builder $administrators) use ($projectId): void {
                        $administrators
                            ->where('system_role', SystemRole::ADMINISTRATOR)
                            ->whereHas(
                                'ownedOrganizations.workspaces.projects',
                                fn (Builder $projects): Builder => $projects->whereKey($projectId)
                            );
                    })
                    ->orWhere(function (Builder $employees) use (
                        $authorApprovalRank,
                        $projectId,
                        $projectWorkspaceId
                    ): void {
                        $employees
                            ->where('system_role', SystemRole::EMPLOYEE)
                            ->where('workspace_id', $projectWorkspaceId)
                            ->whereHas(
                                'projectMemberships',
                                fn (Builder $members): Builder => $members
                                    ->where('project_id', $projectId)
                                    ->where('active', true)
                                    ->where('approval_rank', '>', $authorApprovalRank)
                            );
                    });
            })
            ->get();
    }
}
