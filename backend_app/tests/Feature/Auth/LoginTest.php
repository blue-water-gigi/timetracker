<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

test('Guest can login with valid credentials', function () {
    Plan::factory()->create();
    Organization::factory()->create();
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $response = $this->actingAsGuest()->postJson('/api/v1/login', [
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $response->assertStatus(201);
});

test('Login rejects invalid credentials', function () {
    Plan::factory()->create();
    Organization::factory()->create();
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $response = $this->actingAsGuest()->postJson('/api/v1/login', [
        'email' => 'not_present@mail.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422);
});

test('Auth user cannot login again', function () {
    Plan::factory()->create();
    Organization::factory()->create();
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/login', [
        'email' => 'test@mail.com',
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You are already logged in.',
        ]);
});
