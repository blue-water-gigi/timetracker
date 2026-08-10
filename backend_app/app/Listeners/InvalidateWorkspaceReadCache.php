<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Cache\WorkspaceCacheInvalidator;
use App\Events\WorkspaceReadModelChanged;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class InvalidateWorkspaceReadCache
{
    public function __construct(
        private WorkspaceCacheInvalidator $invalidator,
        private LoggerInterface $logger,
    ) {}

    public function handle(WorkspaceReadModelChanged $event): void
    {
        try {
            $this->invalidator->invalidate($event->workspaceId);
        } catch (Throwable $th) {
            $this->logger->error('Workspace cache invalidation failed.', [
                'workspaceId' => $event->workspaceId,
                'reason'      => $event->reason,
                'exception'   => $th::class,
            ]);
        }
    }
}
