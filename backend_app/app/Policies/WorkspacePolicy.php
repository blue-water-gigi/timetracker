<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\Response;

class WorkspacePolicy
{
    public function viewPersonalStatistics(User $viewer, Workspace $workspace): Response
    {
        if ($viewer->isAdmin()) {
            return $workspace->organization()->where('owner_id', $viewer->getKey())->exists()
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return $viewer->workspace_id === $workspace->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewWorkspaceStatistics(User $viewer, Workspace $workspace): Response
    {
        if ($viewer->isAdmin()) {
            return $workspace->organization()->where('owner_id', $viewer->getKey())->exists()
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return $viewer->workspace_id === $workspace->getKey()
            ? Response::deny('You do not have permission to do this action.')
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organization $organization): Response
    {
        if (! $this->isOwner($user, $organization)) {
            return Response::denyAsNotFound();
        }

        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('You do not have permission to do this action.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workspace $workspace): Response
    {
        return $user->isAdmin()
        && $workspace->organization()->where('owner_id', $user->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Organization $organization): Response
    {
        return $this->viewAny($user, $organization);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workspace $workspace): Response
    {
        return $this->view($user, $workspace);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workspace $workspace): Response
    {
        return $this->view($user, $workspace);
    }

    public function rotateJoinCode(User $user, Workspace $workspace): Response
    {
        return $this->view($user, $workspace);
    }

    private function isOwner(User $user, Organization $organization): bool
    {
        return $user->getKey() === $organization->owner_id;
    }
}
