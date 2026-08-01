<?php

declare(strict_types=1);

namespace App\Services\Project\Actions;

use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Project;
use DB;
use Throwable;

final readonly class DeleteProject
{
    /**
     * @throws TransactionErrorException
     */
    public function handle(Project $project): void
    {
        try {
            DB::transaction(function () use ($project) {
                $project->deleteOrFail();

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: $project->workspace_id,
                    reason: 'project_deleted'
                );
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error deleting timesheet'.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
