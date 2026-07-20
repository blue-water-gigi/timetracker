<?php

namespace App\Http\Controllers\Timesheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timesheet\TimeEntry\StoreTimeEntryRequest;
use App\Http\Requests\Timesheet\TimeEntry\UpdateTimeEntryRequest;
use App\Http\Requests\Timesheet\UpdateTimesheetRequest;
use App\Http\Resources\Timesheet\TimeEntry\TimeEntryResource;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\Workspace;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class TimeEntryController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * @throws Throwable
     */
    public function store(
        StoreTimeEntryRequest $request,
        Workspace             $workspace,
        Project               $project,
        Timesheet             $timesheet): JsonResource
    {
        $entry = $timesheet->addEntry($request->validated());

        return new TimeEntryResource($entry->load('timesheet'));
    }

    /**
     * Update the specified resource in storage.
     * @throws Throwable
     */
    public function update(
        UpdateTimeEntryRequest $request,
        Workspace              $workspace,
        Project                $project,
        Timesheet              $timesheet,
        TimeEntry              $timeEntry): JsonResource
    {
        $timesheet->updateEntry($timeEntry, $request->validated());

        return new TimeEntryResource($timeEntry->load('timesheet'));
    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
     */
    public function destroy(
        Workspace $workspace,
        Project   $project,
        Timesheet $timesheet,
        TimeEntry $timeEntry): JsonResponse
    {
        $timesheet->removeEntry($timeEntry);

        return response()->json(status: 204);
    }
}
