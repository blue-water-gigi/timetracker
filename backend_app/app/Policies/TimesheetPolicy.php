<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TimesheetStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;

class TimesheetPolicy
{

    public function review(User $user, Timesheet $timesheet): Response
    {
        $project = $this->projectFor($timesheet);

        //admin flow
        if ($project === null || !$this->canAccessProjectTenant($user, $project)) {
            return Response::denyAsNotFound();
        }

        //status check
        if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
            return Response::deny('Only submitted timesheets may be reviewed.');
        }

        //user check
        if ($timesheet->user_id === $user->getKey()) {
            return Response::deny('Users cannot review their own timesheets.');
        }

        //check author and approver
        $approver = $this->activeMembership($project, (int)$user->getKey());
        $author = $this->activeMembership($project, (int)$timesheet->user_id);

        if ($approver === null) {
            return Response::deny('An active project membership is required.');
        }

        if ($author === null) {
            return Response::deny('The timesheet author is not an active project member.');
        }

        if ($approver->approval_rank->value <= $author->approval_rank->value) {
            return Response::deny('A higher project role is required.');
        }

        return Response::allow();
    }


    public function approve(User $user, Timesheet $timesheet): Response
    {
        return $this->review($user, $timesheet);
    }

    public function reject(User $user, Timesheet $timesheet): Response
    {
        return $this->review($user, $timesheet);
    }

    public function submit(User $user, Timesheet $timesheet): Response
    {
        return $this->update($user, $timesheet);
    }

    public function viewAny(User $user, Project $project): Response
    {
        if (!$this->canAccessProjectTenant($user, $project)) {
            return Response::denyAsNotFound();
        }

        if ($user->isAdmin()) {
            return Response::allow();
        }

        return $this->activeMembership($project, (int)$user->getKey()) !== null
            ? Response::allow()
            : Response::deny('An active project membership is required.');
    }

    public function view(User $user, Timesheet $timesheet): Response
    {
        $project = $this->projectFor($timesheet);

        if ($project === null || !$this->canAccessProjectTenant($user, $project)) {
            return Response::denyAsNotFound();
        }

        //admin retain readonly access to timesheets belong to org they own.
        if ($user->isAdmin()) {
            return Response::allow();
        }

        $viewer = $this->activeMembership($project, (int)$user->getKey());
        $author = $this->activeMembership($project, (int)$timesheet->user_id);

        if ($viewer === null) {
            return Response::deny('An active project membership is required.');
        }

        if ($timesheet->user_id === $user->getKey()) {
            return Response::allow();
        }

        if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
            return Response::deny();
        }

        if ($author === null) {
            return Response::deny('The timesheet author is not an active project member.');
        }

        if ($viewer->approval_rank->value <= $author->approval_rank->value) {
            return Response::deny('A higher project role is required.');
        }

        return Response::allow();
    }

    public function create(User $user, Project $project): Response
    {
        if (!$this->canAccessProjectTenant($user, $project)) {
            return Response::denyAsNotFound();
        }

        return $this->activeMembership($project, (int)$user->getKey()) !== null
            ? Response::allow()
            : Response::deny('An active project membership is required.');
    }

    public function update(User $user, Timesheet $timesheet): Response
    {
        $project = $this->projectFor($timesheet);

        if ($project === null || !$this->canAccessProjectTenant($user, $project)) {
            return Response::denyAsNotFound();
        }

        if (!in_array(
            $timesheet->status,
            [TimesheetStatus::REJECTED, TimesheetStatus::DRAFT],
            true)) {
            return Response::deny('Only draft or rejected timesheets may be changed.');
        }

        if ($timesheet->user_id !== $user->getKey()) {
            return Response::deny('Only active project membership is required.');
        }

        return $this->activeMembership($project, (int)$user->getKey()) !== null
            ? Response::allow()
            : Response::deny('An active project membership is required.');
    }

    public function delete(User $user, Timesheet $timesheet): Response
    {
        return $this->update($user, $timesheet);
    }

    /**
     * Check that timesheet belongs to certain project
     * and project belongs to certain workspace
     *
     * @param Timesheet $timesheet
     * @return Project|null
     */
    private function projectFor(Timesheet $timesheet): ?Project
    {
        $project = $timesheet->project;

        if (!$project instanceof Project) {
            return null;
        }

        if ($timesheet->workspace_id !== $project->workspace_id) {
            return null;
        }

        return $project;
    }

    /**
     * Check if admin have access to project
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    private function canAccessProjectTenant(User $user, Project $project): bool
    {
        if (!$user->isAdmin()) {
            return $user->workspace_id !== null
                && $user->workspace_id === $project->workspace_id;
        }

        //check if project has workspace that has organization that belongs to user with owner relation
        return $project->workspace()
            ->whereHas(
                'organization',
                fn(Builder $builder): Builder => $builder->whereBelongsTo($user, 'owner')
            )
            ->exists();
    }

    /**
     * @param Project $project
     * @param int $userId
     * @return ProjectMember|null
     */
    private function activeMembership(Project $project, int $userId): ?ProjectMember
    {
        return $project->memberships()
            ->where('user_id', $userId)
            ->where('active', true)
            ->first();
    }
}
