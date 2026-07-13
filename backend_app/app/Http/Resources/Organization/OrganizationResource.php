<?php

namespace App\Http\Resources\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'tin' => $this->tin,
            'currentPlan' => $this->whenNotNull($this->plan),
            'subscriptionStatus' => $this->whenNotNull($this->subscription_status, 'free'),
            'metadata' => $this->whenNotNull($this->metadata),
            'timestamps' => [
                'createdAt' => $this->created_at->format('d-m-Y H:i:s'),
                'updatedAt' => $this->updated_at->format('d-m-Y H:i:s'),
            ],
            'workspaces_count' => $this->whenCounted('workspaces'),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
