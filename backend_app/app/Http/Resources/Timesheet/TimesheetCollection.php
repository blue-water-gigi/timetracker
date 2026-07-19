<?php

namespace App\Http\Resources\Timesheet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TimesheetCollection extends ResourceCollection
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
                'resource' => 'timesheet',
                'multiTenant' => true,
                'includes' => [
                    'project',
                    'user',
                    'reviewedBy',
                    'entries'
                ],
            ],
        ];
    }
}
