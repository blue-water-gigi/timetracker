<?php

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;

beforeEach(function (): void {
    $this->withMiddleware(ThrottleRequestsWithRedis::class);
    $this->withServerVariables([
        'REMOTE_ADDR' => fake()->unique()->ipv4(),
    ]);
});

test('guest can login with valid credentials', function () {
    User::factory()->create([
        'email'    => 'test@mail.com',
        'password' => 'password',
    ]);

    $this->actingAsGuest()->postJson('/api/v1/login', [
        'email'    => 'test@mail.com',
        'password' => 'password',
    ])->assertOk();
});

test('login rejects invalid credentials', function () {
    User::factory()->create([
        'email'    => 'test@mail.com',
        'password' => 'password',
    ]);

    $this->actingAsGuest()->postJson('/api/v1/login', [
        'email'    => 'not_present@mail.com',
        'password' => 'password',
    ])->assertUnauthorized();
});

test('authenticated user cannot login again', function () {
    $user = User::factory()->create([
        'email'    => 'test@mail.com',
        'password' => 'password',
    ]);

    $this->actingAs($user)->postJson('/api/v1/login', [
        'email'    => 'test@mail.com',
        'password' => 'password',
    ])->assertForbidden()
        ->assertJson([
            'message' => 'You are already logged in.',
        ]);
});

test('limits repeated login attempts', function () {
    $payload = [
        'email'    => 'test@example.com',
        'password' => 'wrong-password',
    ];

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/login', $payload)
            ->assertUnauthorized();
    }

    $this->postJson('/api/v1/login', $payload)
        ->assertTooManyRequests()
        ->assertJsonPath('data.errorCode', 'too_many_requests');
});
