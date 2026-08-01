<?php

declare(strict_types=1);

namespace App\Services\Project\Actions;

use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Project;
use DB;
use Throwable;

final readonly class UpdateProject
{
    /**
     * @throws TransactionErrorException
     */
    public function handle(Project $project, array $data, ?int $updatedByUserId): void
    {
        try {
            DB::transaction(function () use ($project, $data, $updatedByUserId) {
                $project->fill($data)
                    ->forceFill([
                        'updated_by_user_id' => $updatedByUserId,
                    ])->saveOrFail();

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: $project->workspace_id,
                    reason: 'project_updated'
                );
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error updating timesheet'.$th->getMessage(),
                $th->getCode(),
                $th
            );
        }
    }
}
