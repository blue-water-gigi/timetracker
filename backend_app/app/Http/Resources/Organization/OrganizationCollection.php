<?php

namespace App\Http\Resources\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrganizationCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource' => 'organizations',
                'includes' => [
                    'workspaces',
                    'workspaces_count',
                    'users_count',
                ]
            ]
        ];
    }
}
