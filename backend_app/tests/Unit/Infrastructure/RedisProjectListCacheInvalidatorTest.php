<?php

declare(strict_types=1);

use App\Infrastructure\Cache\RedisProjectListCacheInvalidator;
use App\Support\Cache\CacheKeys;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Mockery\MockInterface;

afterEach(function (): void {
    Mockery::close();
});

it('flushes only the project list tag for a workspace', function (): void {
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock): void {
        $mock->shouldReceive('flush')->once();
    });
    $cache = Mockery::mock(Repository::class, function (MockInterface $mock) use ($taggedCache): void {
        $mock->shouldReceive('tags')
            ->once()
            ->with([CacheKeys::projectTag(17)])
            ->andReturn($taggedCache);
    });

    (new RedisProjectListCacheInvalidator($cache))->invalidate(17);
});
