<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Timesheet> */
class TimesheetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => fn (array $attributes): int => Project::query()
                ->findOrFail($attributes['project_id'])
                ->workspace_id,
            'user_id' => fn (array $attributes): int => $this->userIdForProject($attributes),
            'period_start' => today()->startOfWeek(),
            'period_end' => today()->endOfWeek(),
            'status' => TimesheetStatus::DRAFT,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Timesheet $timesheet): void {
            ProjectMember::query()->firstOrCreate(
                [
                    'project_id' => $timesheet->project_id,
                    'user_id' => $timesheet->user_id,
                ],
                [
                    'project_role' => ProjectRole::PARTICIPANT,
                    'approval_rank' => ProjectRole::PARTICIPANT->approvalRank(),
                    'active' => true,
                ],
            );
        });
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => TimesheetStatus::SUBMITTED,
            'reviewed_by_user_id' => null,
            'review_comment' => null,
            'reviewed_at' => null,
            'submitted_at' => now(),
        ]);
    }

    public function approved(User $reviewer, ?string $comment = 'Approved.'): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => TimesheetStatus::APPROVED,
            'reviewed_by_user_id' => $reviewer->getKey(),
            'review_comment' => $comment,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(User $reviewer, string $comment = 'Changes requested.'): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => TimesheetStatus::REJECTED,
            'reviewed_by_user_id' => $reviewer->getKey(),
            'review_comment' => $comment,
            'reviewed_at' => now(),
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
