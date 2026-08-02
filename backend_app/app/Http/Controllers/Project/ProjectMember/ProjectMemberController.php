<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project\ProjectMember;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectMember\StoreProjectMember;
use App\Http\Requests\Project\ProjectMember\UpdateProjectMember;
use App\Http\Resources\Project\ProjectMember\ProjectMemberCollection;
use App\Http\Resources\Project\ProjectMember\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Workspace;
use App\Services\ProjectMember\Actions\CreateProjectMember;
use App\Services\ProjectMember\Actions\DeleteProjectMember;
use App\Services\ProjectMember\Actions\UpdateProjectMember as UpdateProjectMemberAction;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class ProjectMemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace, Project $project): JsonResource
    {
        Gate::authorize('viewAny', [ProjectMember::class, $project]);

        return new ProjectMemberCollection(
            $project->memberships()
                ->with(['project', 'user'])
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
    public function store(StoreProjectMember $request, Workspace $workspace, Project $project, CreateProjectMember $action): JsonResource
    {
        Gate::authorize('create', [ProjectMember::class, $project]);

        $member = $action->handle($project, $request->validated());

        return new ProjectMemberResource($member->load(['project', 'user']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace, Project $project, ProjectMember $membership): JsonResource
    {
        Gate::authorize('view', $membership);

        return new ProjectMemberResource($membership->load(['project', 'user']));
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws Throwable
     */
    public function update(
        UpdateProjectMember $request,
        Workspace $workspace,
        Project $project,
        ProjectMember $membership,
        UpdateProjectMemberAction $action): JsonResource
    {
        Gate::authorize('update', $membership);

        $action->handle($membership, $request->validated());

        return new ProjectMemberResource($membership->load(['project', 'user']));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws Throwable
     */
    public function destroy(
        Workspace $workspace,
        Project $project,
        ProjectMember $membership,
        DeleteProjectMember $action): JsonResponse
    {
        Gate::authorize('delete', $membership);

        $action->handle($membership);

        return response()->json(status: 204);
    }
}
