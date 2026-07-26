<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Database\QueryException;

it('builds a tenant-consistent graph from every domain factory', function () {
    $project = Project::factory()->create();
    $membership = ProjectMember::factory()->for($project)->create();
    $timesheet = Timesheet::factory()
        ->for($project)
        ->for($membership->user)
        ->create();
    $entry = TimeEntry::factory()->for($timesheet)->create();

    expect($project->created_by_user_id)
        ->toBe($project->workspace->organization->owner_id)
        ->and($project->updated_by_user_id)
        ->toBe($project->workspace->organization->owner_id)
        ->and($membership->user->workspace_id)
        ->toBe($project->workspace_id)
        ->and($timesheet->workspace_id)
        ->toBe($project->workspace_id)
        ->and($timesheet->user_id)
        ->toBe($membership->user_id)
        ->and($timesheet->entries()->whereKey($entry)->exists())
        ->toBeTrue()
        ->and($entry->work_date->betweenIncluded($timesheet->period_start, $timesheet->period_end))
        ->toBeTrue();
});

it('enforces one membership per user and project', function () {
    $membership = ProjectMember::factory()->create();

    expect(fn () => ProjectMember::factory()
        ->for($membership->project)
        ->for($membership->user)
        ->create())
        ->toThrow(QueryException::class);
});

it('maps role and approval rank before persistence', function () {
    $membership = ProjectMember::factory()
        ->withRole(ProjectRole::MANAGER)
        ->create([
            'approval_rank' => ProjectRole::PARTICIPANT->approvalRank(),
        ]);

    expect($membership->approval_rank)
        ->toBe(ProjectRole::MANAGER->approvalRank());
});

it('enforces PostgreSQL role and workspace checks at the database boundary', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL CHECK constraints require the PostgreSQL CI job.');
    }

    expect(fn () => DB::table('users')->insert([
        'workspace_id' => null,
        'system_role' => 'employee',
        'email' => 'invalid-employee@example.com',
        'password' => 'password',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
