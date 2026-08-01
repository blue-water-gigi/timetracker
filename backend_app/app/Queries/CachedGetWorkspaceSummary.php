<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Queries\GetWorkspaceSummary;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Cache\CacheKeys;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class CachedGetWorkspaceSummary implements GetWorkspaceSummary
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private GetWorkspaceSummary $decorated,
        private Repository $cache,
        private LoggerInterface $logger,
        private ?int $ttl,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function execute(Workspace $workspace, User $viewer): array
    {
        $workspaceId = (int) $workspace->getKey();
        $viewerId = (int) $viewer->getKey();

        $key = CacheKeys::workspaceSummary($workspaceId, $viewerId);

        $tagged = $this->cache->tags([
            CacheKeys::workspaceTag($workspaceId),
        ]);

        // trying to read from cache,
        // if something wrong with cache driver
        // then go straight to Eloquent and return result
        try {
            $cached = $tagged->get($key);
        } catch (Throwable $th) {
            $this->fallback($th, $workspaceId);

            return $this->decorated->execute($workspace, $viewer);
        }

        // if we got result from cache - returning it
        if (is_array($cached)) {
            return $cached;
        }

        // else go to Eloquent and get value from there
        $summary = $this->decorated->execute($workspace, $viewer);

        // then putting it into cache and returning result
        try {
            $tagged->put($key, $summary, $this->ttl);
        } catch (Throwable $th) {
            $this->fallback($th, $workspaceId);
        }

        return $summary;
    }

    /**
     * Give a warning that something wrong with cache storage.
     */
    private function fallback(Throwable $th, int $workspaceId): void
    {
        $this->logger->warning('Workspace summary cache fallback.', [
            'workspaceId' => $workspaceId,
            'exception' => $th::class,
        ]);
    }
}
