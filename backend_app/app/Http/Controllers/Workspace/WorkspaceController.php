<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
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
    public function index(): JsonResource
    {
        return new WorkspaceCollection(
            Workspace::query()
                ->with('organization')
                ->withCount('users')
                ->paginate(20)
                ->withQueryString()
        );
    }

    public function store(StoreWorkspaceRequest $request): JsonResource
    {
        $validated = $request->validated();
        /** @var Organization $organization */
        $organization = $request->user()
            ->ownedOrganizations()
            ->findOrFail($validated['organization_id']);
        $joinCode = Workspace::generateJoinCode();

        $workspace = $organization->workspaces()->make(
            Arr::except($validated, ['organization_id'])
        );
        $workspace->forceFill([
            'join_code_hash' => Workspace::hashJoinCode($joinCode),
        ])->save();

        return (new WorkspaceResource($workspace))->additional([
            'meta' => ['joinCode' => $joinCode],
        ]);
    }

    public function show(Workspace $workspace): JsonResource
    {
        return new WorkspaceResource($workspace);
    }

    /** @throws Throwable */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResource
    {
        $workspace->updateOrFail($request->validated());

        return new WorkspaceResource($workspace);
    }

    /** @throws Throwable */
    public function destroy(Workspace $workspace): JsonResponse
    {
        $workspace->deleteOrFail();

        return response()->json(status: 204);
    }
}
