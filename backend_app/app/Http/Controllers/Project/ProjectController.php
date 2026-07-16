<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectCollection;
use App\Http\Resources\Project\ProjectResource;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace): JsonResource
    {
        return new ProjectCollection(
            $workspace->projects()
                ->with(['memberships', 'workspace'])
                ->withCount('memberships')
                ->paginate(15)
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function store(StoreProjectRequest $request, Workspace $workspace): JsonResource
    {
        $project = $workspace->projects()->make($request->validated());

        $project->forceFill([
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ])->saveOrFail();

        return new ProjectResource($project->load('workspace'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace, Project $project): JsonResource
    {
        return new ProjectResource($project->load('workspace', 'memberships')
            ->loadCount('memberships')
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws Throwable
     */
    public function update(UpdateProjectRequest $request, Workspace $workspace, Project $project): JsonResource
    {
        $project->updateOrFail($request->validated());

        return new ProjectResource($project->load('workspace'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws Throwable
     */
    public function destroy(Workspace $workspace, Project $project): JsonResponse
    {
        $project->deleteOrFail();

        return response()->json(status: 204);
    }
}
