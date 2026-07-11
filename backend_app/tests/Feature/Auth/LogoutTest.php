<?php

use App\Models\User;
use App\Models\Workspace;

test('Authenticated user can logout', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->deleteJson('api/v1/logout')
        ->assertSuccessful()
        ->assertJson([
            'message' => 'User logged out successfully.',
        ]);

    $this->assertGuest('web');
});

test('Guest cannot logout', function () {
    $this->deleteJson('api/v1/logout')->assertUnauthorized();
});
