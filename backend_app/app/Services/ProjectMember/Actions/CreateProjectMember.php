<?php

declare(strict_types=1);

namespace App\Services\ProjectMember\Actions;

use App\Events\ProjectListChanged;
use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Project;
use App\Models\ProjectMember;
use DB;
use Throwable;

final readonly class CreateProjectMember
{
    /**
     * @param array{user_id: int,
     * project_role: string,
     * active: bool} $data
     *
     * @throws TransactionErrorException
     */
    public function handle(Project $project, array $data): ProjectMember
    {
        try {
            return DB::transaction(function () use ($project, $data): ProjectMember {
                $member = new ProjectMember($data);
                $member->project()->associate($project);
                $member->saveOrFail();

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: (int) $member->project()->value('workspace_id'),
                    reason: 'project_member_created',
                );

                ProjectListChanged::dispatch(
                    workspaceId: (int) $member->project()->value('workspace_id'),
                    reason: 'project_member_created'
                );

                return $member;
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error creating timesheet'.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
