<?php

declare(strict_types=1);

namespace App\Services\ProjectMember\Actions;

use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\ProjectMember;
use App\Models\Workspace;
use DB;
use Throwable;

final readonly class DeleteProjectMember
{
    /**
     * @throws TransactionErrorException
     */
    public function handle(Workspace $workspace, ProjectMember $member): void
    {
        try {
            DB::transaction(function () use ($workspace, $member) {
                $member->deleteOrFail();

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: $workspace->id,
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
