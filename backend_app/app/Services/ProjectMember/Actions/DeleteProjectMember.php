<?php

declare(strict_types=1);

namespace App\Services\ProjectMember\Actions;

use App\Events\ProjectListChanged;
use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\ProjectMember;
use DB;
use Throwable;

final readonly class DeleteProjectMember
{
    /**
     * @throws TransactionErrorException
     */
    public function handle(ProjectMember $member): void
    {
        try {
            DB::transaction(function () use ($member) {
                $member->deleteOrFail();

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: (int) $member->project()->value('workspace_id'),
                    reason: 'project_member_deleted',
                );

                ProjectListChanged::dispatch(
                    workspaceId: (int) $member->project()->value('workspace_id'),
                    reason: 'project_member_deleted',
                );
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error deleting project member: '.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
