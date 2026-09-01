<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\TimesheetReviewedNotification;

function reviewedNotification(int $timesheetId, string $decision = 'approved'): TimesheetReviewedNotification
{
    return new TimesheetReviewedNotification([
        'timesheetId'   => $timesheetId,
        'workspaceId'   => 10,
        'projectId'     => 20,
        'reviewerId'    => 30,
        'decision'      => $decision,
        'reviewedAt'    => '2026-08-13T10:00:00+00:00',
        'reviewComment' => null,
    ]);
}

test('guest cannot access notifications', function (): void {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});

test('user sees only own notifications in newest first order with unread count', function (): void {
    $user      = User::factory()->create();
    $otherUser = User::factory()->create();

    $user->notifyNow(reviewedNotification(101));
    $first = $user->notifications()->firstOrFail();
    $first->forceFill(['created_at' => now()->subHour()])->save();
    $user->notifyNow(reviewedNotification(102, 'rejected'));
    $otherUser->notifyNow(reviewedNotification(999));

    $response = $this->actingAs($user)->getJson('/api/v1/notifications');

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'timesheet.reviewed')
        ->assertJsonPath('data.0.payload.timesheetId', 102)
        ->assertJsonPath('data.0.payload.decision', 'rejected')
        ->assertJsonPath('data.0.isRead', false)
        ->assertJsonPath('data.1.payload.timesheetId', 101)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.unreadCount', 2);
});

test('user can mark an own notification as read', function (): void {
    $user = User::factory()->create();
    $user->notifyNow(reviewedNotification(101));
    $notification = $user->notifications()->firstOrFail();

    $this->actingAs($user)
        ->patchJson("/api/v1/notifications/{$notification->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id)
        ->assertJsonPath('data.isRead', true);

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('user cannot mark another users notification as read', function (): void {
    $user      = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUser->notifyNow(reviewedNotification(999));
    $notification = $otherUser->notifications()->firstOrFail();

    $this->actingAs($user)
        ->patchJson("/api/v1/notifications/{$notification->id}")
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

test('user can mark all own notifications as read', function (): void {
    $user      = User::factory()->create();
    $otherUser = User::factory()->create();
    $user->notifyNow(reviewedNotification(101));
    $user->notifyNow(reviewedNotification(102));
    $otherUser->notifyNow(reviewedNotification(999));

    $this->actingAs($user)
        ->patchJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($otherUser->unreadNotifications()->count())->toBe(1);
});
