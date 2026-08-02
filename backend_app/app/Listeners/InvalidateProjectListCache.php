<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Cache\ProjectListCacheInvalidator;
use App\Events\ProjectListChanged;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class InvalidateProjectListCache
{
    public function __construct(
        private LoggerInterface $logger,
        private ProjectListCacheInvalidator $invalidator
    ) {}

    public function handle(ProjectListChanged $event): void
    {
        try {
            $this->invalidator->invalidate($event->workspaceId);
        } catch (Throwable $th) {
            $this->logger->error('Project list cache invalidation failed.', [
                'workspaceId' => $event->workspaceId,
                'reason' => $event->reason,
                'exception' => $th::class,
            ]);
        }
    }
}
