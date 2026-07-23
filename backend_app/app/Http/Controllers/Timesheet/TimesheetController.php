<?php

declare(strict_types=1);

namespace App\Http\Controllers\Timesheet;

use App\Enums\TimesheetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timesheet\ApproveTimesheetRequest;
use App\Http\Requests\Timesheet\RejectTimesheetRequest;
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
    public function index(Request $request, Workspace $workspace, Project $project): JsonResource
    {
        Gate::authorize('viewAny', [Timesheet::class, $project]);

        return new TimesheetCollection(
            $project->timesheets()
                ->visibleTo($request->user(), $project)
                ->with(['project', 'user', 'reviewedBy', 'entries'])
                ->paginate(10)
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function store(StoreTimesheetRequest $request, Workspace $workspace, Project $project): JsonResource
    {
        Gate::authorize('create', [Timesheet::class, $project]);

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
     *
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
     *
     * @throws Throwable
     */
    public function destroy(Workspace $workspace, Project $project, Timesheet $timesheet): JsonResponse
    {
        Gate::authorize('delete', $timesheet);

        $timesheet->deleteOrFail();

        return response()->json(status: 204);
    }

    /**
     * @throws Throwable
     */
    public function submit(Workspace $workspace, Project $project, Timesheet $timesheet): JsonResource
    {
        Gate::authorize('submit', $timesheet);

        $timesheet->submit();

        return new TimesheetResource($timesheet->load(['project', 'user', 'entries']));
    }

    /**
     * @throws Throwable
     */
    public function approve(
        ApproveTimesheetRequest $request,
        Workspace               $workspace,
        Project                 $project,
        Timesheet               $timesheet,
        ?string                 $reviewComment): JsonResource
    {
        Gate::authorize('approve', $timesheet);

        $timesheet->review($request->user(), TimesheetStatus::APPROVED, $reviewComment);

        return new TimesheetResource($timesheet->load(['project', 'user', 'entries', 'reviewedBy']));
    }

    /**
     * @throws Throwable
     */
    public function reject(
        RejectTimesheetRequest $request,
        Workspace              $workspace,
        Project                $project,
        Timesheet              $timesheet,
        ?string                $reviewComment): JsonResource
    {
        Gate::authorize('reject', $timesheet);

        $timesheet->review($request->user(), TimesheetStatus::REJECTED, $reviewComment);

        return new TimesheetResource($timesheet->load(['project', 'user', 'entries', 'reviewedBy']));
    }
}
