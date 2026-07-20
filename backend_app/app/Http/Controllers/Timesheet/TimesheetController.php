<?php

namespace App\Http\Controllers\Timesheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timesheet\StoreTimesheetRequest;
use App\Http\Requests\Timesheet\UpdateTimesheetRequest;
use App\Http\Resources\Timesheet\TimesheetCollection;
use App\Http\Resources\Timesheet\TimesheetResource;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\Workspace;
use DB;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class TimesheetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace, Project $project): JsonResource
    {
        Gate::authorize('viewAny', Timesheet::class);

        return new TimesheetCollection(
            $project->timesheets()
                ->with(['project', 'user', 'reviewedBy', 'entries'])
                ->paginate(10)
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     * @throws Throwable
     */
    public function store(StoreTimesheetRequest $request, Workspace $workspace, Project $project): JsonResource
    {
        GATE::authorize('create', Timesheet::class);

        $timesheet = Timesheet::createForProject($project, $request->user(), $request->validated());

        return new TimesheetResource(
            $timesheet->load(['project', 'user', 'entries'])
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace, Project $project, Timesheet $timesheet): JsonResource
    {
        Gate::authorize('view', $timesheet);

        return new TimesheetResource($timesheet->load(['project', 'user', 'reviewedBy', 'entries']));
    }

    /**
     * Update the specified resource in storage.
     * @throws Throwable
     */
    public function update(
        UpdateTimesheetRequest $request,
        Workspace              $workspace,
        Project                $project,
        Timesheet              $timesheet): JsonResource
    {
        Gate::authorize('update', $timesheet);

        $timesheet->updateOrFail($request->validated());

        return new TimesheetResource($timesheet->load(['project', 'user', 'entries']));
    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
     */
    public function destroy(Workspace $workspace, Project $project, Timesheet $timesheet): JsonResponse
    {
        Gate::authorize('delete', $timesheet);

        $timesheet->deleteOrFail();

        return response()->json(status: 204);
    }
}
