<?php

declare(strict_types=1);

namespace App\Services\Workspace\Actions;

use App\Events\ProjectListChanged;
use App\Exceptions\Transaction\TransactionErrorException;
use App\Models\Workspace;
use DB;
use Throwable;

class UpdateWorkspace
{
    /**
     * @param array{
     *  name:string,
     *  description: string,
     *  active: bool
     * } $data
     *
     * @throws TransactionErrorException
     */
    public function handle(Workspace $workspace, array $data): void
    {
        try {
            DB::transaction(function () use ($workspace, $data) {
                $workspace->updateOrFail($data);

                ProjectListChanged::dispatch(
                    workspaceId: $workspace->getKey(),
                    reason: 'workspace_updated',
                );
            });
        } catch (Throwable $th) {
            throw new TransactionErrorException(
                'Error updating workspace: '.$th->getMessage(),
                $th->getCode(),
                $th,
            );
        }

    }
}
