<?php

declare(strict_types=1);

use App\Enums\ApprovalRank;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use Tests\Support\TenantFixture;

it('lets managers assign roles and derives approval rank on the server', function () {
    $tenant            = TenantFixture::create();
    $manager           = $tenant->employee();
    $managerMembership = $tenant->membership($manager, ProjectRole::MANAGER);
    $target            = $tenant->employee();

    $this->actingAs($manager)
        ->postJson($tenant->projectUrl('members'), [
            'user_id'       => $target->id,
            'project_role'  => ProjectRole::PROJECT_LEAD->value,
            'approval_rank' => ApprovalRank::PARTICIPANT->value,
        ])->assertUnprocessable()
        ->assertJsonValidationErrors('approval_rank');

    $response = $this->postJson($tenant->projectUrl('members'), [
        'user_id'      => $target->id,
        'project_role' => ProjectRole::PROJECT_LEAD->value,
    ])->assertCreated()
        ->assertJsonPath('data.approvalRank', ApprovalRank::PROJECT_LEAD->value);

    $membership = ProjectMember::query()->findOrFail($response->json('data.id'));

    expect($membership->project_role)->toBe(ProjectRole::PROJECT_LEAD)
        ->and($membership->approval_rank)->toBe(ApprovalRank::PROJECT_LEAD);

    $this->patchJson($tenant->projectUrl('members/'.$managerMembership->id), [
        'project_role' => ProjectRole::PROJECT_LEAD->value,
    ])->assertOk()
        ->assertJsonPath('data.approvalRank', ApprovalRank::PROJECT_LEAD->value);
});

it('rejects duplicate and cross-workspace memberships', function () {
    $tenant  = TenantFixture::create();
    $manager = $tenant->employee();
    $tenant->membership($manager, ProjectRole::MANAGER);
    $target        = $tenant->employee();
    $foreignTarget = TenantFixture::create()->employee();

    $this->actingAs($manager)
        ->postJson($tenant->projectUrl('members'), [
            'user_id'      => $target->id,
            'project_role' => ProjectRole::PARTICIPANT->value,
        ])->assertCreated();

    $this->postJson($tenant->projectUrl('members'), [
        'user_id'      => $target->id,
        'project_role' => ProjectRole::PARTICIPANT->value,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');

    $this->postJson($tenant->projectUrl('members'), [
        'user_id'      => $foreignTarget->id,
        'project_role' => ProjectRole::PARTICIPANT->value,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

it('prevents a membership from being resolved through another project', function () {
    $tenant   = TenantFixture::create();
    $employee = $tenant->employee();
    $tenant->membership($employee, ProjectRole::MANAGER);
    $otherProject      = Project::factory()->for($tenant->workspace)->create();
    $foreignMembership = $tenant->membership($tenant->employee(), project: $otherProject);

    $this->actingAs($employee)
        ->getJson($tenant->projectUrl('members/'.$foreignMembership->id))
        ->assertNotFound();
});
