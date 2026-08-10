<?php

declare(strict_types=1);

namespace App\Http\Resources\Workspace;

use App\Http\Resources\Organization\OrganizationResource;
use App\Http\Resources\Project\ProjectCollection;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
class WorkspaceResource extends JsonResource
{
    private ?array $summary = null;

    public function withSummary(?array $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'description'  => $this->whenNotNull($this->description),
            'active'       => $this->active,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'projects'     => new ProjectCollection($this->whenLoaded('projects')),

            'summary' => $this->whenNotNull($this->formatSummary($this->summary)),

            'timestamps' => [
                'createdAt' => $this->created_at?->toISOString(),
                'updatedAt' => $this->updated_at?->toISOString(),
            ],
            'usersCount' => $this->whenCounted('users'),
        ];
    }

    public function formatSummary(?array $summary): ?array
    {
        return $summary === null
            ? null
            : [
                'workspaceId'          => $summary['workspace_id']           ?? null,
                'projectsCount'        => $summary['projects_count']         ?? 0,
                'membersCount'         => $summary['members_count']          ?? 0,
                'validTimesheetsCount' => $summary['valid_timesheets_count'] ?? 0,
            ];
    }
}
