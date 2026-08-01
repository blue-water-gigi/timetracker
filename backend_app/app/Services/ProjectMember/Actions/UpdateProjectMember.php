<?php

declare(strict_types=1);

namespace App\Services\ProjectMember\Actions;

use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\ProjectMember;
use App\Models\Workspace;
use DB;
use Throwable;

final readonly class UpdateProjectMember
{
    /**
     * @param array{project_role:string,
     * active: bool} $data
     *
     * @throws TransactionErrorException
     */
    public function handle(Workspace $workspace, ProjectMember $member, array $data): void
    {
        try {
            DB::transaction(function () use ($workspace, $member, $data) {
                $member->updateOrFail($data);

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: $workspace->id,
                    reason: 'project_member_updated',
                );
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error updating project member: '.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
