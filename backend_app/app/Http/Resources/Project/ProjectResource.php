<?php

declare(strict_types=1);

namespace App\Http\Resources\Project;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\Workspace\WorkspaceResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
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
            'createdBy' => new UserResource($this->whenLoaded('createdBy')),
            'updatedBy' => new UserResource($this->whenNotNull($this->whenLoaded('updatedBy'))),
            'name' => $this->name,
            'description' => $this->whenNotNull($this->description),
            'slug' => $this->slug,
            'active' => $this->active,
            'periodStart' => $this->whenNotNull($this->period_start),
            'periodEnd' => $this->whenNotNull($this->period_end),
            'timestamps' => [
                'createdAt' => $this->created_at?->toIsoString(),
                'updatedAt' => $this->updated_at?->toIsoString(),
            ],

            'memberships' => $this->whenLoaded('memberships'), // todo add resource
            'membershipsCount' => $this->whenCounted('memberships'),
        ];
    }
}
