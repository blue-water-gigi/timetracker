<?php

use App\Enums\SystemRole;
use App\Models\User;

test('guest admin can register without join-code', function () {
    $response = $this->postJson(route('register.admin'), [
        'first_name' => 'test',
        'last_name' => 'test',
        'email' => 'test@mail.com',
        'password' => 'password',
    ])->assertCreated();

    $user = User::query()->where('email', 'test@mail.com')->firstOrFail();

    expect($user->exists())->toBeTrue()
        ->and($user->system_role)->toBe(SystemRole::ADMINISTRATOR);
    $response->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.systemRole', 'admin');
});

test('admin registration requires a valid payload', function () {
    $response = $this->postJson(route('register.admin'), [
        'first_name' => '1',
        'last_name' => 'z',
        'email' => 'test_mail.com',
        'password' => 'pa2ss',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'first_name',
            'last_name',
            'email',
            'password',
        ]);
});

test('admin registration cannot accept prohibited fields', function () {
    $response = $this->postJson(route('register.admin'), [
        'first_name' => 'test',
        'last_name' => 'test',
        'email' => 'test@mail.com',
        'password' => 'password',
        'join_code' => 'some_code',
        'workspace_id' => 1,
        'system_role' => 'admin',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['workspace_id', 'system_role', 'join_code']);
});

test('authenticated user cannot register again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('register.admin'), [
        'join_code' => 'irrelevant',
        'email' => 'second@mail.com',
        'password' => 'password',
    ])->assertForbidden();

    expect(User::query()->where('email', 'second@mail.com')->doesntExist())->toBeTrue();
});
