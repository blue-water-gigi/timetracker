<?php

namespace App\Http\Resources\Workspace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->whenNotNull($this->description),
            'active' => $this->whenNotNull($this->active, true),
            'organization' => $this->organization,
            'timestamps' => [
                'createdAt' => $this->created_at->format('d-m-Y H:i:s'),
                'updatedAt' => $this->updated_at->format('d-m-Y H:i:s'),
            ],
            'countUsers' => $this->whenCounted('users'),
        ];
    }
}
