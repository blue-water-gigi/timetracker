<?php

use App\Models\User;

test('guest can login with valid credentials', function () {
    User::factory()->create([
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $this->actingAsGuest()->postJson('/api/v1/login', [
        'email' => 'test@mail.com',
        'password' => 'password',
    ])->assertCreated();
});

test('login rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $this->actingAsGuest()->postJson('/api/v1/login', [
        'email' => 'not_present@mail.com',
        'password' => 'password',
    ])->assertUnprocessable();
});

test('authenticated user cannot login again', function () {
    $user = User::factory()->create([
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $this->actingAs($user)->postJson('/api/v1/login', [
        'email' => 'test@mail.com',
        'password' => 'password',
    ])->assertForbidden()
        ->assertJson([
            'message' => 'You are already logged in.',
        ]);
});
