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

    public function viewStatistics(User $viewer, Project $project): Response
    {
        return $this->view($viewer, $project);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): Response
    {
        if ($this->ownsProject($user, $project)) {
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
        return $this->ownsWorkspace($user, $workspace)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): Response
    {
        return $this->ownsProject($user, $project)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): Response
    {
        return $this->update($user, $project);
    }

    private function ownsProject(User $user, Project $project): bool
    {
        return $user->isAdmin()
            && $project->workspace()
                ->whereRelation('organization', 'owner_id', $user->getKey())
                ->exists();
    }

    private function ownsWorkspace(User $user, Workspace $workspace): bool
    {
        return $user->isAdmin()
            && $workspace->organization()
                ->where('owner_id', $user->getKey())
                ->exists();
    }
}
