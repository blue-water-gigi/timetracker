<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\Workspace\WorkspaceCollection;
use App\Http\Resources\Workspace\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class WorkspaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkspaceRequest $request): JsonResource
    {
        $validated = $request->validated();

        $workspace = Workspace::query()->create($validated);

        return new WorkspaceResource($workspace);
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace): JsonResource
    {
        return new WorkspaceResource($workspace);
    }

    /**
     * Update the specified resource in storage.
     * @throws Throwable
     */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResource
    {
        $validated = $request->validated();

        $workspace->updateOrFail($validated);

        return new WorkspaceResource($workspace);
    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
     */
    public function destroy(Workspace $workspace): JsonResponse
    {
        $workspace->deleteOrFail();

        return response()->json(status: 204);
    }
}
