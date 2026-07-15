<?php

use App\Models\User;

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->deleteJson('/api/v1/logout')
        ->assertSuccessful()
        ->assertNoContent();

    $this->assertGuest('web');
});

test('guest cannot logout', function () {
    $this->deleteJson('/api/v1/logout')->assertUnauthorized();
});
