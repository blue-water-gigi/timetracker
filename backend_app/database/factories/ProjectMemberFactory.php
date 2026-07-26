<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectMember> */
class ProjectMemberFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $attributes): int => $this->userIdForProject($attributes),
            'project_role' => ProjectRole::PARTICIPANT,
            'approval_rank' => ProjectRole::PARTICIPANT->approvalRank(),
            'active' => true,
        ];
    }

    public function withRole(ProjectRole $role): static
    {
        return $this->state(fn (): array => [
            'project_role' => $role,
            'approval_rank' => $role->approvalRank(),
        ]);
    }

    /** @param array{project_id: int} $attributes */
    private function userIdForProject(array $attributes): int
    {
        $workspaceId = (int) Project::query()
            ->whereKey($attributes['project_id'])
            ->value('workspace_id');
        $workspace = Workspace::query()->findOrFail($workspaceId);

        return (int) User::factory()->forWorkspace($workspace)->create()->getKey();
    }
}
