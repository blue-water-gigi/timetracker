<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Workspace $workspace): Response
    {
        if ($this->ownsWorkspace($user, $workspace)) {
            return Response::allow();
        }

        return $user->workspace_id === $workspace->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewSelfProjects(User $user, Workspace $workspace): Response
    {
        return $user->workspace_id === $workspace->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): Response
    {
        // todo add functionality where projects can be viewed by some other users (semi-admins etc.)

        if ($this->ownsWorkspace($user, $project->workspace)) {
            return Response::allow();
        }

        if ($user->workspace_id !== $project->workspace_id) {
            return Response::denyAsNotFound();
        }

        return $project->memberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->exists()
            ? Response::allow()
            : Response::deny('Active membership is required.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Workspace $workspace): Response
    {
        // todo add functionality where projects can be created not only by 'admin'

        return $this->ownsWorkspace($user, $workspace)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): Response
    {
        // todo add functionality where projects can be updated not only by 'admin'
        return $this->ownsWorkspace($user, $project->workspace)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): Response
    {
        // todo add functionality where projects can be deleted not only by 'admin'
        return $this->update($user, $project);
    }

    private function ownsWorkspace(User $user, Workspace $workspace): bool
    {
        return $user->isAdmin()
            && $workspace->organization()
                ->where('owner_id', $user->getKey())
                ->exists();
    }
}
