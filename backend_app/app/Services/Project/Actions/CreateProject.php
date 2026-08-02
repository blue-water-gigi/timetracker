<?php

declare(strict_types=1);

namespace App\Services\Project\Actions;

use App\Events\ProjectListChanged;
use App\Events\WorkspaceReadModelChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Project;
use App\Models\Workspace;
use DB;
use Throwable;

final readonly class CreateProject
{
    /**
     * @param array{
     *     name: string,
     *     description?: string|null,
     *     slug: string,
     *     active?: bool,
     *     period_start?: string|null,
     *     period_end?: string|null
     * } $data
     *
     * @throws TransactionErrorException
     */
    public function handle(Workspace $workspace, array $data, ?int $userId): Project
    {
        try {
            return DB::transaction(function () use ($workspace, $data, $userId) {
                $project = $workspace->projects()->make($data);

                $project->forceFill([
                    'created_by_user_id' => $userId,
                    'updated_by_user_id' => $userId,
                ])->saveOrFail();

                WorkspaceReadModelChanged::dispatch(
                    workspaceId: $project->workspace_id,
                    reason: 'project_created'
                );

                ProjectListChanged::dispatch(
                    workspaceId: $project->workspace_id,
                    reason: 'project_created'
                );

                return $project;
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
