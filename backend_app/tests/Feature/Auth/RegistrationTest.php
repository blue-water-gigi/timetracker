<?php

use App\Models\User;
use App\Models\Workspace;

test('Guest can register', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson('/api/v1/register', [
        'workspace_id' => $workspace->id,
        'nickname' => 'test',
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $response->assertSuccessful();

    $user = User::where('email', $response->json()['user']['email'])->first();

    expect($user)->not()->toBeNull()
        ->and($user)->toMatchArray([
            'nickname' => 'test',
            'first_name' => null,
            'last_name' => null,
            'system_role' => 'user',
            'email' => 'test@mail.com',
        ])
        ->and($response->json())->toMatchArray([
            'message' => 'User successfully registered.',
            'user' => [
                'id' => $user->id,
                'workspace_id' => $user->workspace_id,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'updated_at' => $user->updated_at->toJSON(),
                'created_at' => $user->updated_at->toJSON(),
            ],
        ]);
});

test('Registration Requires valid payload', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson('/api/v1/register', [
        'workspace_id' => 444,
        'nickname' => 'test sadfdsf',
        'email' => 'invalid_mail.com',
        'password' => 'passw',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'workspace_id', 'nickname', 'email', 'password',
        ]);

    expect(User::count())->toBe(0);
});

test('User cannot register with admin role', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson('/api/v1/register', [
        'workspace_id' => $workspace->id,
        'nickname' => 'test',
        'system_role' => 'admin',
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('system_role');
});

test('Authenticated user cannot register again', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/register', $user->toArray());

    expect($response->getStatusCode())->not()->toBe([200, 201])
        ->and(User::count())->toBe(1);
});
