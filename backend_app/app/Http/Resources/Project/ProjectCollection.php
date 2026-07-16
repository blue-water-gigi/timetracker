<?php

declare(strict_types=1);

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProjectCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource' => 'project',
                'multiTenant' => true,
                'filters' => [
                    'search' => $request->query('search'),
                    'name' => $request->query('name'),
                ],
                'includes' => [
                    'workspace',
                    'memberships',
                    'membershipsCount',
                ],
            ],
        ];
    }
}
