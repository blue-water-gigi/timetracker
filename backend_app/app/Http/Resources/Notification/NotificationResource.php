<?php

declare(strict_types=1);

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => (string) $this->resource->getKey(),
            'type'      => $this->type,
            'payload'   => $this->data,
            'isRead'    => $this->read_at !== null,
            'readAt'    => $this->read_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
