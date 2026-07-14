<?php

declare(strict_types=1);

namespace App\Http\Resources\Organization;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'archivedAt' => $this->archived_at?->toISOString(),
            'timestamps' => [
                'createdAt' => $this->created_at?->toISOString(),
                'updatedAt' => $this->updated_at?->toISOString(),
            ],
            'workspacesCount' => $this->whenCounted('workspaces'),
            'usersCount' => $this->whenCounted('users'),
        ];
    }
}
