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
use Auth;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace): JsonResource
    {
        Gate::authorize('viewAny', [Project::class, $workspace]);

        return new ProjectCollection(
            $workspace->projects()
                ->with(['memberships', 'workspace', 'createdBy', 'updatedBy'])
                ->withCount('memberships')
                ->paginate(15)
                ->withQueryString()
        );
    }

    public function showMyProjects(Workspace $workspace): JsonResource
    {
        Gate::authorize('viewSelfProjects', [Project::class, $workspace]);

        return new ProjectCollection(
            Auth::user()->projects()
                ->with(['workspace', 'createdBy', 'updatedBy'])
                ->withCount('memberships')
                ->paginate(10)
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
        Gate::authorize('create', [Project::class, $workspace]);

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
        Gate::authorize('view', $project);

        return new ProjectResource($project->load(['memberships', 'workspace', 'createdBy', 'updatedBy'])
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
        Gate::authorize('update', $project);

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
        Gate::authorize('delete', $project);

        $project->deleteOrFail();

        return response()->json(status: 204);
    }
}
