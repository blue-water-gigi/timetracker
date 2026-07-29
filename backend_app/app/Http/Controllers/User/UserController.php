<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Throwable;

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

        return new UserResource($user->load(['workspace', 'ownedOrganizations', 'projects', 'projectMemberships'])
            ->loadCount('projects'));
    }

    /**
     * @throws Throwable
     */
    public function destroy(Workspace $workspace, User $user): JsonResponse
    {
        Gate::authorize('delete', [$user, $workspace]);

        $user->deleteOrFail();

        return response()->json(status: 204);
    }
}
