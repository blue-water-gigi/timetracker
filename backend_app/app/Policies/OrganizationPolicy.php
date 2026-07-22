<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        if (!$this->isOwner($user, $user->ownedOrganizations)) {
            return Response::denyAsNotFound();
        }

        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('You do not have permission to view organizations.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Organization $organization): Response
    {
        if (!$this->isOwner($user, $user->ownedOrganizations)) {
            return Response::denyAsNotFound();
        }

        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('You do not have permission to do this action.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        if (!$this->isOwner($user, $user->ownedOrganizations)) {
            return Response::denyAsNotFound();
        }

        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('You do not have permission to do this action.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organization $organization): Response
    {
        return $this->view($user, $organization);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organization $organization): Response
    {
        return $this->view($user, $organization);
    }

    public function isOwner(User $user, Organization $organization): bool
    {
        return $user->getKeyName() === $organization->getForeignKey();
    }
}
