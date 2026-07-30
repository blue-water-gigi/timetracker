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
use App\Services\Timesheet\TimesheetService;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class TimeEntryController extends Controller
{
    public function __construct(private readonly TimesheetService $timesheetService) {}

    /**
     * @throws Throwable
     */
    public function store(
        StoreTimeEntryRequest $request,
        Workspace $workspace,
        Project $project,
        Timesheet $timesheet): JsonResource
    {
        Gate::authorize('update', $timesheet);

        $entry = $this->timesheetService->addEntry($timesheet, $request->entryData());

        return new TimeEntryResource($entry->load('timesheet'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws Throwable
     */
    public function update(
        UpdateTimeEntryRequest $request,
        Workspace $workspace,
        Project $project,
        Timesheet $timesheet,
        TimeEntry $entry): JsonResource
    {
        Gate::authorize('update', $timesheet);

        $entry = $this->timesheetService->updateEntry($timesheet, $entry, $request->entryData());

        return new TimeEntryResource($entry->load('timesheet'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws Throwable
     */
    public function destroy(
        Workspace $workspace,
        Project $project,
        Timesheet $timesheet,
        TimeEntry $entry): JsonResponse
    {
        Gate::authorize('delete', $timesheet);

        $this->timesheetService->removeEntry($timesheet, $entry);

        return response()->json(status: 204);
    }
}
