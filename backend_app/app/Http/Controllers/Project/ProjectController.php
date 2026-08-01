<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectCollection;
use App\Http\Resources\Project\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Project\Actions\CreateProject;
use App\Services\Project\Actions\DeleteProject;
use App\Services\Project\Actions\UpdateProject;
use Auth;
use Gate;
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
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('viewAny', [Project::class, $workspace]);

        return new ProjectCollection(
            Project::query()->visibleTo($user, $workspace)
                ->with(['workspace', 'createdBy', 'updatedBy'])
                ->withCount('memberships')
                ->latest()
                ->paginate(15)
                ->withQueryString()
        );
    }

    public function showMyProjects(Workspace $workspace): JsonResource
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('viewSelfProjects', [Project::class, $workspace]);

        return new ProjectCollection(
            Project::query()->visibleTo($user, $workspace)
                ->with(['workspace', 'createdBy', 'updatedBy'])
                ->withCount('memberships')
                ->latest()
                ->paginate(10)
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function store(StoreProjectRequest $request, Workspace $workspace, CreateProject $action): JsonResource
    {
        Gate::authorize('create', [Project::class, $workspace]);

        $project = $action->handle($workspace, $request->validated(), $request->user()?->id);

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
    public function update(UpdateProjectRequest $request, Workspace $workspace, Project $project, UpdateProject $action): JsonResource
    {
        Gate::authorize('update', $project);

        $action->handle($project, $request->validated(), $request->user()?->id);

        return new ProjectResource($project->load('workspace'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws Throwable
     */
    public function destroy(Workspace $workspace, Project $project, DeleteProject $action): JsonResponse
    {
        Gate::authorize('delete', $project);

        $action->handle($project);

        return response()->json(status: 204);
    }
}
