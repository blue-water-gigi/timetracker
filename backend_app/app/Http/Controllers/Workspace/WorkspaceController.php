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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Throwable;

class WorkspaceController extends Controller
{
    public function index(Organization $organization): JsonResource
    {
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
        //todo add policy
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
        $workspace->updateOrFail($request->validated());

        return new WorkspaceResource($workspace);
    }

    /** @throws Throwable */
    public function destroy(Organization $organization, Workspace $workspace): JsonResponse
    {
        $workspace->deleteOrFail();

        return response()->json(status: 204);
    }

    /**
     * Refresh the join code
     *
     * @param Organization $organization
     * @param Workspace $workspace
     * @return JsonResource
     */
    public function rotateJoinCode(RotateJoinCodeRequest $request, Organization $organization, Workspace $workspace): JsonResource
    {
        $joinCode = $workspace->rotateJoinCode();

        return new WorkspaceResource($workspace->load('organization'))
            ->additional([
                'meta' => [
                    'joinCode' => $joinCode,
                ]
            ]);
    }
}
