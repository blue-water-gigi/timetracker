<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ProjectRole;
use App\Enums\TimesheetStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Timesheet\Data\TimesheetPeriodData;
use App\Services\Timesheet\TimesheetService;

final readonly class TenantFixture
{
    public function __construct(
        public User $admin,
        public Organization $organization,
        public Workspace $workspace,
        public Project $project,
    ) {}

    public static function create(): self
    {
        $admin = User::factory()->administrator()->create();
        $organization = Organization::factory()->for($admin, 'owner')->create();
        $workspace = Workspace::factory()->for($organization)->create();
        $project = Project::factory()->for($workspace)->create();

        return new self($admin, $organization, $workspace, $project);
    }

    public function employee(): User
    {
        return User::factory()->forWorkspace($this->workspace)->create();
    }

    public function membership(
        User $user,
        ProjectRole $role = ProjectRole::PARTICIPANT,
        bool $active = true,
        ?Project $project = null,
    ): ProjectMember {
        return ProjectMember::factory()
            ->for($project ?? $this->project)
            ->for($user)
            ->withRole($role)
            ->create(['active' => $active]);
    }

    public function timesheet(
        User $user,
        TimesheetStatus $status = TimesheetStatus::DRAFT,
        ?Project $project = null,
    ): Timesheet {
        $project ??= $this->project;

        if (! ProjectMember::query()
            ->whereBelongsTo($project)
            ->whereBelongsTo($user)
            ->exists()) {
            $this->membership($user, project: $project);
        }

        $weekOffset = Timesheet::query()
            ->whereBelongsTo($project)
            ->whereBelongsTo($user)
            ->count();
        $periodStart = today()->startOfWeek()->addWeeks($weekOffset);
        $service = app(TimesheetService::class);
        $timesheet = $service->create(
            $project,
            $user,
            TimesheetPeriodData::fromValidated([
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodStart->copy()->endOfWeek()->toDateString(),
            ]),
        );

        return match ($status) {
            TimesheetStatus::DRAFT => $timesheet,
            TimesheetStatus::SUBMITTED => $service->submit($timesheet),
            TimesheetStatus::APPROVED => $service->approve($this->admin, $service->submit($timesheet), 'Fixture approval.'),
            TimesheetStatus::REJECTED => $service->reject($this->admin, $service->submit($timesheet), 'Fixture rejection.'),
        };
    }

    public function projectUrl(string $suffix = ''): string
    {
        $base = "/api/v1/workspaces/{$this->workspace->id}/projects/{$this->project->id}";

        return $suffix === '' ? $base : $base.'/'.ltrim($suffix, '/');
    }

    public function workspaceUrl(string $suffix = ''): string
    {
        $base = "/api/v1/organizations/{$this->organization->id}/workspaces/{$this->workspace->id}";

        return $suffix === '' ? $base : $base.'/'.ltrim($suffix, '/');
    }
}
