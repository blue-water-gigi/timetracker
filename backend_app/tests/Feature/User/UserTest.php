<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;

test('user can view all users of same workspace', function () {
    $workspace = Workspace::factory()->create();
    $users = User::factory(5)->forWorkspace($workspace)->create();

    $firstUser = $users->get(1);

    $this->actingAs($firstUser)->getJson("/api/v1/workspaces/{$workspace->id}/users")
        ->assertSuccessful();
});

test('user cannot view any users of different workspace', function () {
    $workspaces = Workspace::factory(2)->create();
    $firstWorkspace = $workspaces->first();
    $secondWorkspace = $workspaces->last();

    $users = User::factory(5)->forWorkspace($firstWorkspace)->create();
    $anotherBatch = User::factory(5)->forWorkspace($secondWorkspace)->create();

    $firstUser = $users->first();

    $this->actingAs($firstUser)->getJson("/api/v1/workspaces/{$secondWorkspace->id}/users")
        ->assertNotFound();
});

test('admin or user of "n" workspace can view user of "n" workspace', function () {
    $organization = Organization::factory()->create();
    $workspace = Workspace::factory()->for($organization)->create();
    $users = User::factory(5)->forWorkspace($workspace)->create();

    $firstUser = $users->first();

    $differentUser = $users->last();

    $admin = $organization->owner()->first();

    $this->actingAs($firstUser)->getJson("/api/v1/workspaces/{$workspace->id}/users/{$differentUser->id}")
        ->assertSuccessful();
    $this->actingAs($admin)->getJson("/api/v1/workspaces/{$workspace->id}/users/{$differentUser->id}")
        ->assertSuccessful();
});

test('User of "n" workspace cannot view user of "z" workspace', function () {
    $organization = Organization::factory()->create();
    $workspaces = Workspace::factory(2)->for($organization)->create();
    $firstWorkspace = $workspaces->first();
    $lastWorkspace = $workspaces->last();

    $users = User::factory(5)->forWorkspace($firstWorkspace)->create();
    $users2 = User::factory(5)->forWorkspace($lastWorkspace)->create();

    $firstUser = $users->first();
    $differentUser = $users2->first();

    $this->actingAs($firstUser)->getJson("/api/v1/workspaces/{$firstWorkspace->id}/users/{$differentUser->id}")
        ->assertNotFound();
});

it('soft deletes user', function () {
    $organization = Organization::factory()->create();
    $workspace = Workspace::factory()->for($organization)->create();
    $user = User::factory()->forWorkspace($workspace)->create();

    $admin = $organization->owner()->first();

    $this->actingAs($admin)->deleteJson("/api/v1/workspaces/{$workspace->id}/users/{$user->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $user->id]);
});
