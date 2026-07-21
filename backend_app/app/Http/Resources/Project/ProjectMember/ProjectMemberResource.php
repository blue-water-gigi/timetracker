<?php

declare(strict_types=1);

namespace App\Http\Resources\Project\ProjectMember;

use App\Http\Resources\Project\ProjectResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**@mixin ProjectMember */
class ProjectMemberResource extends JsonResource
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
            'project' => new ProjectResource($this->whenLoaded('project')),
            'user' => new UserResource($this->whenLoaded('user')),
            'projectRole' => $this->project_role,
            'approvalRank' => $this->approval_rank,
            'active' => $this->active,
            'timestamps' => [
                'createdAt' => $this->created_at?->toIsoString(),
                'updatedAt' => $this->updated_at?->toIsoString(),
            ],
        ];
    }
}
