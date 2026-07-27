<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user, Workspace $workspace): Response
    {
        if ($this->ownsWorkspace($user, $workspace)) {
            return Response::allow();
        }

        return $this->belongsToActiveWorkspace($user, $workspace)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, User $target, Workspace $workspace): Response
    {
        return $this->viewAny($user, $workspace);
    }

    public function delete(User $user, User $target, Workspace $workspace): Response
    {
        return $this->ownsWorkspace($user, $workspace)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    //    public function restore(User $user, User $model): bool
    //    {
    //        // todo
    //    }

    //    public function forceDelete(User $user, User $model): bool
    //    {
    //        // todo
    //    }

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
