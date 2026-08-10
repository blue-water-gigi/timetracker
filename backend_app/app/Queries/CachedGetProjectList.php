<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Queries\GetProjectList;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Cache\CacheKeys;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class CachedGetProjectList implements GetProjectList
{
    public function __construct(
        private GetProjectList $decorator,
        private Repository $cache,
        private LoggerInterface $logger,
        private ?int $ttl,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function execute(Workspace $workspace, User $viewer, int $page = 1, int $perPage = 15): array
    {
        $workspaceId = (int) $workspace->getKey();
        $viewerId    = (int) $viewer->getKey();

        $key = CacheKeys::projectList($workspaceId, $viewerId, $page, $perPage);

        try {
            $tagged = $this->cache->tags([
                CacheKeys::projectTag($workspaceId),
            ]);

            $cached = $tagged->get($key);
        } catch (Throwable $th) {
            $this->fallback($th, $workspaceId);

            return $this->decorator->execute($workspace, $viewer, $page, $perPage);
        }

        if (is_array($cached)) {
            return $cached;
        }

        $list = $this->decorator->execute($workspace, $viewer, $page, $perPage);

        try {
            $tagged->put($key, $list, $this->ttl);
        } catch (Throwable $th) {
            $this->fallback($th, $workspaceId);
        }

        return $list;
    }

    /**
     * Give a warning that something wrong with cache storage.
     */
    private function fallback(Throwable $th, int $workspaceId): void
    {
        $this->logger->warning('Project list cache fallback.', [
            'workspaceId' => $workspaceId,
            'exception'   => $th::class,
        ]);
    }
}
