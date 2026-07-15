<?php

use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Workspace;

test('guest employee can register with a workspace join code', function () {
    $joinCode = 'valid-workspace-join-code';
    $workspace = Workspace::factory()->withJoinCode($joinCode)->create();

    $response = $this->postJson(route('register.employee'), [
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'join_code' => $joinCode,
        'email' => 'test@mail.com',
        'password' => 'password',
    ])->assertCreated();

    $user = User::query()->where('email', 'test@mail.com')->firstOrFail();

    expect($user->workspace_id)->toBe($workspace->id)
        ->and($user->system_role)->toBe(SystemRole::EMPLOYEE);

    $response->assertJsonPath('data.email', 'test@mail.com')
        ->assertJsonPath('data.systemRole', 'employee');
});

test('registration rejects an invalid join code', function () {
    $this->postJson(route('register.employee'), [
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'join_code' => 'not-present',
        'email' => 'test@mail.com',
        'password' => 'password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('join_code');

    expect(User::query()->count())->toBe(0);
});

test('registration requires a valid payload', function () {
    $this->postJson(route('register.employee'), [
        'workspace_id' => 444,
        'first_name' => 'x',
        'join_code' => 'present-but-not-checked-yet',
        'email' => 'invalid_mail.com',
        'password' => 'passw',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'workspace_id',
            'first_name',
            'email',
            'password',
        ]);
});

test('public registration cannot set protected tenant or role fields', function () {
    $joinCode = 'protected-fields-test';
    $workspace = Workspace::factory()->withJoinCode($joinCode)->create();

    $this->postJson(route('register.employee'), [
        'workspace_id' => $workspace->id,
        'system_role' => 'admin',
        'join_code' => $joinCode,
        'email' => 'test@mail.com',
        'password' => 'password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['workspace_id', 'system_role']);
});

test('authenticated user cannot register again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('register.employee'), [
        'join_code' => 'irrelevant',
        'email' => 'second@mail.com',
        'password' => 'password',
    ])->assertForbidden();

    expect(User::query()->where('email', 'second@mail.com')->doesntExist())->toBeTrue();
});
