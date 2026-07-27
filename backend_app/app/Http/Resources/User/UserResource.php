<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Http\Resources\Organization\OrganizationCollection;
use App\Http\Resources\Project\ProjectCollection;
use App\Http\Resources\Project\ProjectMember\ProjectMemberCollection;
use App\Http\Resources\Timesheet\TimesheetCollection;
use App\Http\Resources\Workspace\WorkspaceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->whenNotNull($this->first_name),
            'lastName' => $this->whenNotNull($this->last_name),
            'systemRole' => $this->system_role,
            'email' => $this->email,
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'ownedOrganizations' => new OrganizationCollection($this->whenLoaded('ownedOrganizations')),
            'projects' => new ProjectCollection($this->whenLoaded('projects')),
            'projectsCount' => $this->whenCounted('projects'),
            'projectMemberships' => new ProjectMemberCollection($this->whenLoaded('projectMemberships')),
            'timesheets' => new TimesheetCollection($this->whenLoaded('timesheets')),
            'timestamps' => [
                'createdAt' => $this->created_at?->toIsoString(),
                'updatedAt' => $this->updated_at?->toIsoString(),
            ],
        ];
    }
}
