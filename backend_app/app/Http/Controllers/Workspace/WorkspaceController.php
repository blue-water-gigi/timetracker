<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\RotateJoinCodeRequest;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\Workspace\WorkspaceCollection;
use App\Http\Resources\Workspace\WorkspaceResource;
use App\Models\Organization;
use App\Models\Workspace;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class WorkspaceController extends Controller
{
    public function index(Organization $organization): JsonResource
    {
        Gate::authorize('viewAny', [Workspace::class, $organization]);

        return new WorkspaceCollection(
            $organization->workspaces()
                ->with('organization')
                ->withCount('users')
                ->paginate(15)
                ->withQueryString()
        );
    }

    public function store(StoreWorkspaceRequest $request, Organization $organization): JsonResource
    {
        Gate::authorize('create', [Workspace::class, $organization]);

        $validated = $request->validated();

        $joinCode = Workspace::generateJoinCode();

        $workspace = $organization->workspaces()->make($validated);

        $workspace->forceFill([
            'join_code_hash' => Workspace::hashJoinCode($joinCode),
        ])->save();

        return new WorkspaceResource($workspace->load('organization'))->additional([
            'meta' => ['joinCode' => $joinCode],
        ]);
    }

    public function show(Organization $organization, Workspace $workspace): JsonResource
    {
        Gate::authorize('view', $workspace);

        return new WorkspaceResource(
            $workspace->load('organization')->loadCount('users')
        );
    }

    /** @throws Throwable */
    public function update(
        UpdateWorkspaceRequest $request,
        Organization           $organization,
        Workspace              $workspace
    ): JsonResource
    {
        Gate::authorize('update', $workspace);

        $workspace->updateOrFail($request->validated());

        return new WorkspaceResource($workspace);
    }

    /** @throws Throwable */
    public function destroy(Organization $organization, Workspace $workspace): JsonResponse
    {
        Gate::authorize('delete', $workspace);

        $workspace->deleteOrFail();

        return response()->json(status: 204);
    }

    /**
     * Refresh the join code
     */
    public function rotateJoinCode(RotateJoinCodeRequest $request, Organization $organization, Workspace $workspace): JsonResource
    {
        Gate::authorize('rotateJoinCode', $workspace);

        $joinCode = $workspace->rotateJoinCode();

        return new WorkspaceResource($workspace->load('organization'))
            ->additional([
                'meta' => [
                    'joinCode' => $joinCode,
                ],
            ]);
    }
}
