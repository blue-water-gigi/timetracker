<?php

declare(strict_types=1);

namespace App\Http\Controllers\Timesheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timesheet\TimeEntry\StoreTimeEntryRequest;
use App\Http\Requests\Timesheet\TimeEntry\UpdateTimeEntryRequest;
use App\Http\Resources\Timesheet\TimeEntry\TimeEntryResource;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\Workspace;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class TimeEntryController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function store(
        StoreTimeEntryRequest $request,
        Workspace             $workspace,
        Project               $project,
        Timesheet             $timesheet): JsonResource
    {
        Gate::authorize('create', [TimeEntry::class, $project]);

        $entry = $timesheet->addEntry($request->validated());

        return new TimeEntryResource($entry->load('timesheet'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws Throwable
     */
    public function update(
        UpdateTimeEntryRequest $request,
        Workspace              $workspace,
        Project                $project,
        Timesheet              $timesheet,
        TimeEntry              $timeEntry): JsonResource
    {
        Gate::authorize('update', $timesheet);

        $timesheet->updateEntry($timeEntry, $request->validated());

        return new TimeEntryResource($timeEntry->load('timesheet'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws Throwable
     */
    public function destroy(
        Workspace $workspace,
        Project   $project,
        Timesheet $timesheet,
        TimeEntry $timeEntry): JsonResponse
    {
        Gate::authorize('delete', $timesheet);

        $timesheet->removeEntry($timeEntry);

        return response()->json(status: 204);
    }
}
