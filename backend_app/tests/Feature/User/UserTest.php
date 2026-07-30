<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Tests\Support\TenantFixture;

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

    $this->getJson("/api/v1/workspaces/{$workspace->id}/users/{$user->id}")->assertNotFound();
});

it('lists only users from the requested workspace', function () {
    $tenant = TenantFixture::create();
    $users = User::factory(3)->forWorkspace($tenant->workspace)->create();
    $foreignTenant = TenantFixture::create();
    $foreignUser = $foreignTenant->employee();
    $viewer = $users->first();

    $response = $this->actingAs($viewer)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/users")
        ->assertOk()
        ->assertJsonMissing(['password']);

    $userIds = collect($response->json('data'))->pluck('id');

    expect($userIds)
        ->toHaveCount(3)
        ->toContain($viewer->id)
        ->not->toContain($foreignUser->id);
});

it('hides a workspace from an administrator who does not own it', function () {
    $tenant = TenantFixture::create();
    $target = $tenant->employee();
    $foreignTenant = TenantFixture::create();

    $this->actingAs($foreignTenant->admin)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/users")
        ->assertNotFound();

    $this->getJson("/api/v1/workspaces/{$tenant->workspace->id}/users/{$target->id}")
        ->assertNotFound();

    $this->deleteJson("/api/v1/workspaces/{$tenant->workspace->id}/users/{$target->id}")
        ->assertNotFound();

    expect($target->refresh()->deleted_at)->toBeNull();
});

it('does not let an employee delete a workspace colleague', function () {
    $tenant = TenantFixture::create();
    $actor = $tenant->employee();
    $target = $tenant->employee();

    $this->actingAs($actor)
        ->deleteJson("/api/v1/workspaces/{$tenant->workspace->id}/users/{$target->id}")
        ->assertNotFound();

    expect($target->refresh()->deleted_at)->toBeNull();
});

it('denies an employee access to an inactive workspace but keeps owner access', function () {
    $tenant = TenantFixture::create();
    $employee = $tenant->employee();
    $tenant->workspace->updateOrFail(['active' => false]);

    $this->actingAs($employee)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/users")
        ->assertNotFound();

    $this->actingAs($tenant->admin)
        ->getJson("/api/v1/workspaces/{$tenant->workspace->id}/users")
        ->assertOk();
});

it('requires authentication for every user endpoint', function () {
    $tenant = TenantFixture::create();
    $target = $tenant->employee();

    $this->getJson("/api/v1/workspaces/{$tenant->workspace->id}/users")->assertUnauthorized();
    $this->getJson("/api/v1/workspaces/{$tenant->workspace->id}/users/{$target->id}")
        ->assertUnauthorized();
    $this->deleteJson("/api/v1/workspaces/{$tenant->workspace->id}/users/{$target->id}")
        ->assertUnauthorized();
});
