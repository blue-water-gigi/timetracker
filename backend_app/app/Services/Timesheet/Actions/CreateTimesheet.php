<?php

namespace App\Services\Timesheet\Actions;

use App\Exceptions\Domain\DuplicateTimesheetPeriodException;
use App\Exceptions\Domain\ProjectMembershipRequiredException;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Timesheet\Data\TimesheetPeriodData;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CreateTimesheet
{
    /**
     * @throws TransactionErrorException
     * @throws ProjectMembershipRequiredException
     */
    public function handle(Project $project, User $author, TimesheetPeriodData $data): Timesheet
    {
        try {
            return DB::transaction(function () use ($project, $author, $data) {
                $activeMember = $project->memberships()
                    ->whereBelongsTo($project)
                    ->whereBelongsTo($author)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if (!$activeMember) {
                    throw ProjectMembershipRequiredException::make();
                }

                $timesheet = new Timesheet();

                $timesheet->forceFill([
                    'workspace_id' => $project->workspace_id,
                    'project_id' => $project->id,
                    'user_id' => $author->id,
                    ...$data->toArray(),
                ])->saveOrFail();

                return $timesheet;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw DuplicateTimesheetPeriodException::make();
        } catch (Throwable $e) {
            throw new TransactionErrorException('Error creating timesheet', 500, $e);
        }
    }
}
