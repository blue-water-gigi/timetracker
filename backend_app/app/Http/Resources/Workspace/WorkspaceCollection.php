<?php

declare(strict_types=1);

namespace App\Http\Resources\Workspace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WorkspaceCollection extends ResourceCollection
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

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource' => 'workspace',
                'multiTenant' => true,
                'filters' => [
                    'search' => $request->query('search'),
                    'name' => $request->query('name'),
                ],
                'includes' => [
                    'organization',
                    'users_count',
                ],
            ],
        ];
    }
}
