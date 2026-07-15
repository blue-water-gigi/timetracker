<?php

use App\Enums\ApprovalRank;
use App\Enums\ProjectRole;
use App\Enums\SystemRole;
use App\Enums\TimesheetStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;

test('model defaults and protected attributes follow the domain contract', function () {
    $user = new User([
        'workspace_id' => 999,
        'system_role' => SystemRole::ADMINISTRATOR,
        'email' => 'employee@example.com',
        'password' => 'password',
    ]);

    expect($user->workspace_id)->toBeNull()
        ->and($user->system_role)->toBe(SystemRole::EMPLOYEE)
        ->and((new Workspace)->active)->toBeTrue()
        ->and((new Project)->active)->toBeTrue()
        ->and((new Timesheet)->status)->toBe(TimesheetStatus::DRAFT);
});

test('workspace stores only a join code digest and supports lookup', function () {
    $joinCode = 'a-high-entropy-test-join-code';
    $workspace = Workspace::factory()->withJoinCode($joinCode)->create();

    expect($workspace->join_code_hash)->toBe(Workspace::hashJoinCode($joinCode))
        ->and($workspace->toArray())->not->toHaveKey('join_code_hash')
        ->and(Workspace::query()->whereJoinCode($joinCode)->firstOrFail()->is($workspace))->toBeTrue();
});

test('custom project pivot maps role to rank and maintains timestamps', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->forWorkspace($workspace)->create();
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $project->users()->attach($user->id, [
        'project_role' => ProjectRole::SENIOR,
        'approval_rank' => ApprovalRank::PARTICIPANT,
        'active' => true,
    ]);

    $membership = ProjectMember::query()->firstOrFail();

    expect($membership->project_role)->toBe(ProjectRole::SENIOR)
        ->and($membership->approval_rank)->toBe(ApprovalRank::SENIOR)
        ->and($membership->created_at)->not->toBeNull()
        ->and($membership->updated_at)->not->toBeNull()
        ->and($project->users()->firstOrFail()->pivot)->toBeInstanceOf(ProjectMember::class);
});

test('role and approval rank mapping is deterministic', function (ProjectRole $role, ApprovalRank $rank) {
    expect($role->approvalRank())->toBe($rank);
})->with([
    'participant' => [ProjectRole::PARTICIPANT, ApprovalRank::PARTICIPANT],
    'senior' => [ProjectRole::SENIOR, ApprovalRank::SENIOR],
    'manager' => [ProjectRole::MANAGER, ApprovalRank::MANAGER],
    'project lead' => [ProjectRole::PROJECT_LEAD, ApprovalRank::PROJECT_LEAD],
]);

test('timesheet creation enforces active membership and derives tenant fields', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->forWorkspace($workspace)->create();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $project->users()->attach($user->id, [
        'project_role' => ProjectRole::PARTICIPANT,
        'active' => true,
    ]);

    $timesheet = Timesheet::createForProject($project, $user, [
        'period_start' => '2026-07-13',
        'period_end' => '2026-07-19',
    ]);
    $entry = $timesheet->addEntry([
        'work_date' => '2026-07-14',
        'hours' => 8,
    ]);

    expect($timesheet->workspace_id)->toBe($workspace->id)
        ->and($timesheet->project_id)->toBe($project->id)
        ->and($timesheet->user_id)->toBe($user->id)
        ->and($timesheet->status)->toBe(TimesheetStatus::DRAFT)
        ->and($entry->hours)->toBe('8.00')
        ->and($entry->timesheet->is($timesheet))->toBeTrue();
});

test('timesheet rejects non-members and out-of-period entries', function () {
    $workspace = Workspace::factory()->create();
    $member = User::factory()->forWorkspace($workspace)->create();
    $outsider = User::factory()->forWorkspace($workspace)->create();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    expect(fn () => Timesheet::createForProject($project, $outsider, [
        'period_start' => '2026-07-13',
        'period_end' => '2026-07-19',
    ]))->toThrow(DomainException::class);

    $project->users()->attach($member->id, [
        'project_role' => ProjectRole::PARTICIPANT,
        'active' => true,
    ]);
    $timesheet = Timesheet::createForProject($project, $member, [
        'period_start' => '2026-07-13',
        'period_end' => '2026-07-19',
    ]);

    expect(fn () => $timesheet->addEntry([
        'work_date' => '2026-07-20',
        'hours' => 8,
    ]))->toThrow(DomainException::class);
});

test('restrict foreign keys preserve tenant aggregates', function () {
    $workspace = Workspace::factory()->create();
    $organization = $workspace->organization;

    expect(fn () => $organization->delete())->toThrow(QueryException::class)
        ->and(Organization::query()->whereKey($organization)->exists())->toBeTrue();
});
