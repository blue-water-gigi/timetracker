<?php

declare(strict_types=1);

namespace App\Services\ProjectMember\Actions;

use App\Events\ProjectListChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\ProjectMember;
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
    public function handle(ProjectMember $member, array $data): void
    {
        try {
            DB::transaction(function () use ($member, $data) {
                $member->updateOrFail($data);

                ProjectListChanged::dispatch(
                    workspaceId: (int) $member->project()->value('workspace_id'),
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
