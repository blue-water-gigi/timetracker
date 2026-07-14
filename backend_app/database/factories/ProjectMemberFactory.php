<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectMember> */
class ProjectMemberFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
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
}
