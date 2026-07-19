<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectMembershipPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Project $project): Response
    {
        $canView = ($user->isAdmin() && $project->workspace->organization->owner_id === $user->getKey())
            || $user->projectMemberships()
                ->whereBelongsTo($project)
                ->where('active', true)
                ->exists();

        return $canView
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProjectMember $membership): Response
    {
        $canView = ($user->isAdmin() && $user->ownedOrganizations()->exists())
            || $membership->query()->where('active', true);

        return $canView
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Project $project): Response
    {
        return $this->canManage($user, $project)
            ? Response::allow()
            : Response::deny("You don't have permission to do this action.");
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProjectMember $membership): Response
    {
        return $this->create($user, $membership->project);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProjectMember $membership): Response
    {
        return $this->update($user, $membership);
    }

    private function canManage(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return $project->workspace->organization->owner_id === $user->getKey();
        }

        return $project->memberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->whereIn('project_role', [ProjectRole::PROJECT_LEAD, ProjectRole::MANAGER])
            ->exists();
    }
}
