<?php

namespace App\Http\Controllers\Project\ProjectMember;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectMember\StoreProjectMember;
use App\Http\Requests\Project\ProjectMember\UpdateProjectMember;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Resources\Project\ProjectMember\ProjectMemberCollection;
use App\Http\Resources\Project\ProjectMember\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Workspace;
use DB;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                ->paginate(10)
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     * @throws Throwable
     */
    public function store(StoreProjectMember $request, Workspace $workspace, Project $project): JsonResource
    {
        Gate::authorize('create', [ProjectMember::class, $project]);

        $member = DB::transaction(function () use ($request, $workspace, $project) {
            $member = $project->memberships()->make($request->validated());

            $member->forceFill([
                'approval_rank' => $member->project_role->approvalRank(),
            ])->saveOrFail();

            return $member;
        });

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
     * @throws Throwable
     */
    public function update(
        UpdateProjectMember $request,
        Workspace           $workspace,
        Project             $project,
        ProjectMember       $membership): JsonResource
    {
        Gate::authorize('update', $membership);

        $membership->updateOrFail($request->validated());

        return new ProjectMemberResource($membership->load(['project', 'user']));
    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
     */
    public function destroy(Workspace $workspace, Project $project, ProjectMember $membership): JsonResponse
    {
        Gate::authorize('delete', $membership);

        $membership->deleteOrFail();

        return response()->json(status: 204);
    }
}
