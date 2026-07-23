<?php

declare(strict_types=1);

namespace App\Http\Resources\Timesheet;

use App\Http\Resources\Project\ProjectResource;
use App\Http\Resources\Timesheet\TimeEntry\TimeEntryResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Workspace\WorkspaceResource;
use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Timesheet
 */
class TimesheetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'createdBy' => new UserResource($this->whenLoaded('user')),
            'periodStart' => $this->period_start->toIsoString(),
            'periodEnd' => $this->period_end->toIsoString(),
            'status' => $this->status,
            'entries' => TimeEntryResource::collection($this->whenLoaded('entries')),
            'reviewedBy' => new UserResource($this->whenLoaded('reviewedBy')),
            'reviewComment' => $this->whenNotNull($this->review_comment),
            'timestamps' => [
                'submittedAt' => $this->whenNotNull($this->submitted_at?->toIsoString()),
                'reviewedAt' => $this->whenNotNull($this->reviewed_at?->toIsoString()),
                'createdAt' => $this->created_at?->toIsoString(),
                'updatedAt' => $this->updated_at?->toIsoString(),
            ],
        ];
    }
}
