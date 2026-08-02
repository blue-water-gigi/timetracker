<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;

beforeEach(function (): void {
    $this->withMiddleware(ThrottleRequestsWithRedis::class);
    $this->withServerVariables([
        'REMOTE_ADDR' => fake()->unique()->ipv4(),
    ]);
});

it('normalizes email before validation, persistence and authentication', function () {
    $email = fake()->unique()->safeEmail();

    $this->postJson('/api/v1/register/admin', [
        'email' => '  '.strtoupper($email).'  ',
        'password' => 'password123',
    ])->assertCreated();

    expect(User::query()->where('email', $email)->exists())->toBeTrue();
});

it('authenticates with a normalized email address', function () {
    $email = fake()->unique()->safeEmail();
    User::factory()->create([
        'email' => $email,
        'password' => 'password123',
    ]);

    $this->postJson('/api/v1/login', [
        'email' => '  '.strtoupper($email).'  ',
        'password' => 'password123',
    ])->assertOk();
});

it('uses one normalized login bucket and forwards retry headers', function () {
    $email = fake()->unique()->safeEmail();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/login', [
            'email' => $attempt % 2 === 0 ? strtoupper($email) : $email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/v1/login', [
        'email' => strtoupper($email),
        'password' => 'wrong-password',
    ])->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('data.errorCode', 'too_many_requests');
});

it('keeps administrator and employee registration rate-limit buckets separate', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/register/admin', [
            'email' => "invalid-admin-{$attempt}",
            'password' => 'short',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/register/admin', [
        'email' => 'sixth-invalid-admin',
        'password' => 'short',
    ])->assertTooManyRequests();

    $this->postJson('/api/v1/register/employee', [
        'email' => 'invalid-employee',
        'password' => 'short',
        'join_code' => 'invalid',
    ])->assertUnprocessable();
});
