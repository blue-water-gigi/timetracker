<?php

declare(strict_types=1);

namespace App\Http\Resources\Workspace;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
class WorkspaceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->whenNotNull($this->description),
            'active' => $this->active,
            'organization' => $this->whenLoaded('organization'),
            'timestamps' => [
                'createdAt' => $this->created_at?->toISOString(),
                'updatedAt' => $this->updated_at?->toISOString(),
            ],
            'usersCount' => $this->whenCounted('users'),
        ];
    }
}
