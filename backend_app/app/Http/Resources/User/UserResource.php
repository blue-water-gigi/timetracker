<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->whenNotNull($this->first_name),
            'lastName' => $this->whenNotNull($this->last_name),
            'systemRole' => $this->system_role,
            'email' => $this->email,
            'timestamps' => [
                'createdAt' => $this->created_at->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updated_at->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
