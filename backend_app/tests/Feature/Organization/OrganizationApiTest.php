<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Models\User;
use Tests\Support\TenantFixture;

it('lets an administrator manage only owned organizations', function () {
    $tenant  = TenantFixture::create();
    $foreign = TenantFixture::create();

    $this->actingAs($tenant->admin)
        ->getJson('/api/v1/organizations/'.$tenant->organization->id)
        ->assertOk()
        ->assertJsonPath('data.id', $tenant->organization->id);

    $this->getJson('/api/v1/organizations/'.$foreign->organization->id)
        ->assertNotFound();

    $created = $this->postJson('/api/v1/organizations', [
        'name' => 'New organization',
    ])->assertCreated();

    $organizationId = $created->json('data.id');

    $this->patchJson('/api/v1/organizations/'.$organizationId, [
        'name' => 'Renamed organization',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Renamed organization');

    expect(Organization::query()->findOrFail($organizationId)->owner->is($tenant->admin))->toBeTrue();
});

it('soft deletes the tenant hierarchy while preserving time history', function () {
    $tenant   = TenantFixture::create();
    $employee = User::factory()->forWorkspace($tenant->workspace)->create();
    ProjectMember::factory()->for($tenant->project)->for($employee)->create();
    $timesheet = Timesheet::factory()->for($tenant->project)->for($employee)->create([
        'workspace_id' => $tenant->workspace->id,
    ]);
    $entry = $timesheet->entries()->create([
        'work_date' => $timesheet->period_start,
        'hours'     => 8,
    ]);

    $this->actingAs($tenant->admin)
        ->deleteJson('/api/v1/organizations/'.$tenant->organization->id)
        ->assertNoContent();

    $this->assertSoftDeleted('organizations', ['id' => $tenant->organization->id]);
    $this->assertSoftDeleted('workspaces', ['id' => $tenant->workspace->id]);
    $this->assertSoftDeleted('projects', ['id' => $tenant->project->id]);
    $this->assertDatabaseHas('timesheets', ['id' => $timesheet->id]);
    $this->assertDatabaseHas('time_entries', ['id' => $entry->id]);

    $this->getJson('/api/v1/organizations/'.$tenant->organization->id)
        ->assertNotFound();
});
