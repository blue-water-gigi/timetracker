<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Contracts\Cache\ProjectListCacheInvalidator;
use App\Support\Cache\CacheKeys;
use Illuminate\Contracts\Cache\Repository;

readonly class RedisProjectListCacheInvalidator implements ProjectListCacheInvalidator
{
    public function __construct(
        private Repository $cache,
    ) {}

    public function invalidate(int $workspaceId): void
    {
        $this->cache->tags([CacheKeys::projectTag($workspaceId)])->flush();
    }
}
