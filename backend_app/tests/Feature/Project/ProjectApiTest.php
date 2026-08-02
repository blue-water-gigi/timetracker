<?php

declare(strict_types=1);

use App\Models\Project;
use Tests\Support\TenantFixture;

it('validates project periods consistently on create and partial update', function () {
    $tenant = TenantFixture::create();
    $this->actingAs($tenant->admin);

    $this->postJson('/api/v1/workspaces/'.$tenant->workspace->id.'/projects', [
        'name' => 'Invalid project',
        'slug' => 'invalid-project',
        'period_start' => '2026-08-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('period_end');

    $this->postJson('/api/v1/workspaces/'.$tenant->workspace->id.'/projects', [
        'name' => 'Reverse project',
        'slug' => 'reverse-project',
        'period_start' => '2026-08-10',
        'period_end' => '2026-08-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('period_end');

    $this->patchJson($tenant->projectUrl(), [
        'period_start' => $tenant->project->period_end->addDay()->toDateString(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('period_start');

    $this->patchJson($tenant->projectUrl(), [
        'period_start' => null,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('period_start');

    $this->patchJson($tenant->projectUrl(), [
        'description' => null,
        'period_start' => null,
        'period_end' => null,
    ])->assertOk();

    expect($tenant->project->refresh()->period_start)->toBeNull()
        ->and($tenant->project->period_end)->toBeNull()
        ->and($tenant->project->description)->toBeNull();
});

it('returns only active projects visible through active membership', function () {
    $tenant = TenantFixture::create();
    $employee = $tenant->employee();
    $tenant->membership($employee);
    $hidden = Project::factory()->for($tenant->workspace)->create();
    $inactive = Project::factory()->for($tenant->workspace)->create(['active' => false]);
    $tenant->membership($employee, project: $inactive);

    $response = $this->actingAs($employee)
        ->getJson('/api/v1/workspaces/'.$tenant->workspace->id.'/projects')
        ->assertOk();

    $projectIds = collect($response->json('data'))->pluck('id');

    expect($projectIds)->toContain($tenant->project->id)
        ->not->toContain($hidden->id)
        ->not->toContain($inactive->id);
});

it('hides foreign tenant projects and soft deletes owned projects', function () {
    $tenant = TenantFixture::create();
    $foreign = TenantFixture::create();

    $this->actingAs($tenant->admin)
        ->getJson('/api/v1/workspaces/'.$tenant->workspace->id.'/projects/'.$foreign->project->id)
        ->assertNotFound();

    $this->deleteJson($tenant->projectUrl())
        ->assertNoContent();

    $this->assertSoftDeleted('projects', ['id' => $tenant->project->id]);
    $this->getJson($tenant->projectUrl())->assertNotFound();
});
