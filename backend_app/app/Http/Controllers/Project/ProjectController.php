<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Contracts\Queries\GetProjectList;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ShowProjectListRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Project\Actions\CreateProject;
use App\Services\Project\Actions\DeleteProject;
use App\Services\Project\Actions\UpdateProject;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectController extends Controller
{
    public function index(ShowProjectListRequest $request, Workspace $workspace, GetProjectList $list): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', [Project::class, $workspace]);

        $projects = $list->execute(
            $workspace,
            $user,
            page: $request->integer('page', 1),
            perPage: $request->integer('perPage', 15),
        );

        return response()->json($projects);
    }

    /**
     * @throws TransactionErrorException
     */
    public function store(StoreProjectRequest $request, Workspace $workspace, CreateProject $action): JsonResource
    {
        Gate::authorize('create', [Project::class, $workspace]);

        $project = $action->handle($workspace, $request->validated(), $request->user()?->id);

        return new ProjectResource($project->load('workspace'));
    }

    public function show(Workspace $workspace, Project $project): JsonResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($project->load(['memberships', 'workspace', 'createdBy', 'updatedBy'])
            ->loadCount('memberships')
        );
    }

    /**
     * @throws TransactionErrorException
     */
    public function update(UpdateProjectRequest $request, Workspace $workspace, Project $project, UpdateProject $action): JsonResource
    {
        Gate::authorize('update', $project);

        $action->handle($project, $request->validated(), $request->user()?->id);

        return new ProjectResource($project->load('workspace'));
    }

    /**
     * @throws TransactionErrorException
     */
    public function destroy(Workspace $workspace, Project $project, DeleteProject $action): JsonResponse
    {
        Gate::authorize('delete', $project);

        $action->handle($project);

        return response()->json(status: 204);
    }
}
