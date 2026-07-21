<?php

declare(strict_types=1);

namespace App\Http\Resources\Timesheet\TimeEntry;

use App\Http\Resources\Timesheet\TimesheetResource;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeEntry
 */
class TimeEntryResource extends JsonResource
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
            'timesheet' => new TimesheetResource($this->whenLoaded('timesheet')),
            'workDate' => $this->work_date,
            'description' => $this->whenNotNull($this->description),
            'hours' => $this->hours,
            'isOvertime' => $this->is_overtime,
            'timestamps' => [
                'createdAt' => $this->created_at->toIsoString(),
                'updatedAt' => $this->updated_at->toIsoString(),
            ],
        ];
    }
}
