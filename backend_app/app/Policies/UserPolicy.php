<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Workspace $workspace): Response
    {
        if ($this->ownsWorkspace($user, $workspace)) {
            return Response::allow();
        }

        return $this->belongsToActiveWorkspace($user, $workspace)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workspace $workspace): Response
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workspace $workspace): Response
    {
        return $this->ownsWorkspace($user, $workspace)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        //todo
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        //todo
    }

    private function ownsWorkspace(User $user, Workspace $workspace): bool
    {
        return $user->isAdmin()
            && $workspace->organization()
                ->whereBelongsTo($user, 'owner')
                ->exists();
    }

    private function belongsToActiveWorkspace(User $user, Workspace $workspace): bool
    {
        return $user->workspace()
            ->whereKey($workspace->getKey())
            ->where('active', true)
            ->exists();
    }
}
