<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return NotificationResource::collection($notifications)->additional([
            'meta' => [
                'unreadCount' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function update(Request $request, string $notification): NotificationResource
    {
        $databaseNotification = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $databaseNotification->markAsRead();

        return new NotificationResource($databaseNotification);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'unreadCount' => 0,
            ],
        ]);
    }
}
