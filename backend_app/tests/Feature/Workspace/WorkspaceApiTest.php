<?php

declare(strict_types=1);

use App\Models\Workspace;
use Tests\Support\TenantFixture;

it('creates a workspace without exposing its join code digest', function () {
    $tenant = TenantFixture::create();

    $response = $this->actingAs($tenant->admin)
        ->postJson('/api/v1/organizations/'.$tenant->organization->id.'/workspaces', [
            'name' => 'Backend team',
            'description' => null,
        ])
        ->assertCreated()
        ->assertJsonMissingPath('data.join_code_hash');

    $workspace = Workspace::query()->findOrFail($response->json('data.id'));
    $joinCode = $response->json('meta.joinCode');

    expect($joinCode)->toBeString()->not->toBeEmpty()
        ->and($workspace->join_code_hash)->toBe(Workspace::hashJoinCode($joinCode));
});

it('rotates a join code and invalidates the old one', function () {
    $tenant = TenantFixture::create();
    $oldHash = $tenant->workspace->join_code_hash;

    $response = $this->actingAs($tenant->admin)
        ->postJson($tenant->workspaceUrl('rotate-join-code'))
        ->assertOk();

    $joinCode = $response->json('meta.joinCode');

    expect($tenant->workspace->refresh()->join_code_hash)
        ->not->toBe($oldHash)
        ->toBe(Workspace::hashJoinCode($joinCode));
});

it('uses scoped bindings and archives a workspace with its projects', function () {
    $tenant = TenantFixture::create();
    $foreign = TenantFixture::create();

    $this->actingAs($tenant->admin)
        ->getJson('/api/v1/organizations/'.$tenant->organization->id.'/workspaces/'.$foreign->workspace->id)
        ->assertNotFound();

    $this->deleteJson($tenant->workspaceUrl())
        ->assertNoContent();

    $this->assertSoftDeleted('workspaces', ['id' => $tenant->workspace->id]);
    $this->assertSoftDeleted('projects', ['id' => $tenant->project->id]);
});
