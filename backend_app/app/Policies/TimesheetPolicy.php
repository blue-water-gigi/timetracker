<?php

namespace App\Policies;

use App\Enums\ApprovalRank;
use App\Enums\ProjectRole;
use App\Enums\TimesheetStatus;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Throwable;

class TimesheetPolicy
{
    /**
     * @return Response
     * @throws Throwable
     */
    public function review(User $user, Timesheet $timesheet): Response
    {
        if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
            return Response::deny('Only submitted timesheets may be reviewed.');
        }

        if ($timesheet->user_id === $user->id) {
            return Response::deny('User cannot review his own timesheets.');
        }

        // should be project member
        $approver = $timesheet->project->memberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->firstOrFail();

        //should be a project member
        $author = $timesheet->project->memberships()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->firstOrFail();

        $mayReview = $approver->exists
            && $approver->approval_rank->value > $author->approval_rank->value
            && $author->exists;

        return $mayReview
            ? Response::allow()
            : Response::deny('A higher project role is required.');
    }

    public function approve(User $user, Timesheet $timesheet): Response
    {
        return $this->review($user, $timesheet);
    }

    public function reject(User $user, Timesheet $timesheet): Response
    {
        return $this->approve($user, $timesheet);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        return $this->view($user, $user->timesheets);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Timesheet $timesheet): Response
    {

        $approver = $timesheet->project->memberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->firstOrFail();

        $author = $timesheet->project->memberships()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->firstOrFail();

        if (!$author->exists || !$approver->exists) {
            return Response::deny('Approver or author doesnt exist.');
        }

        //author can view his own timesheet and reviewer can view timesheets of members
        if ($user->id === $author->id ||
            ($user->id === $approver->id && $approver->approval_rank->value > $author->approval_rank->value)) {
            return Response::allow();
        }

        return Response::deny('A higher project role is required.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        return $user->projectMemberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Timesheet $timesheet): Response
    {
        $canUpdate = $timesheet->status === (TimesheetStatus::DRAFT || TimesheetStatus::REJECTED)
            && $user->id === $timesheet->user_id
            && $user->projectMemberships()
                ->whereBelongsTo($user)
                ->where('active', true)
                ->exists();

        $canUpdate
            ? Response::allow()
            : Response::deny('You are not allowed to do this action.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Timesheet $timesheet): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('You are not allowed to do this action.');
    }
}
