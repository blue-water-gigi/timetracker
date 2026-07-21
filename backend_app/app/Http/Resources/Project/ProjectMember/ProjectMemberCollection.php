<?php

declare(strict_types=1);

namespace App\Http\Resources\Project\ProjectMember;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProjectMemberCollection extends ResourceCollection
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
                'resource' => 'projectMember',
                'includes' => [
                    'user',
                    'project',
                ],
            ],
        ];
    }
}
