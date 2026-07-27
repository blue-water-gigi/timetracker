<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace): JsonResource
    {
        Gate::authorize('viewAny', [User::class, $workspace]);

        $users = $workspace->users()->get();

        return UserResource::collection(
            $users->load('workspace')->loadCount('projects')
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace, User $user): JsonResource
    {
        Gate::authorize('view', [$user, $workspace]);


        return new UserResource($user->load(['workspace', 'ownedOrganizations', 'projects', 'projectMemberships', 'projectsCount']));
    }

    public function destroy(): JsonResponse
    {
        //todo make soft delete on Users.
        //admin can 'archive' user
        // nobody else can
    }
}
