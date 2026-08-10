<?php

declare(strict_types=1);

use App\Contracts\Queries\GetProjectList;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\CachedGetProjectList;
use App\Support\Cache\CacheKeys;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

afterEach(function (): void {
    Mockery::close();
});

it('returns a tagged cached project list without executing the database query', function (): void {
    [$workspace, $viewer] = projectListModels();
    $list                 = projectListPayload();

    $databaseQuery = Mockery::mock(GetProjectList::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('execute');
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock) use ($list): void {
        $mock->shouldReceive('get')
            ->once()
            ->with(CacheKeys::projectList(17, 501, 2, 10))
            ->andReturn($list);
    });

    $query = new CachedGetProjectList(
        decorator: $databaseQuery,
        cache: projectListCacheRepository($taggedCache),
        logger: Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer, 2, 10))->toBe($list);
});

it('loads and stores a project list on a tagged cache miss', function (): void {
    [$workspace, $viewer] = projectListModels();
    $list                 = projectListPayload();

    $databaseQuery = Mockery::mock(GetProjectList::class, function (MockInterface $mock) use ($workspace, $viewer, $list): void {
        $mock->shouldReceive('execute')
            ->once()
            ->with($workspace, $viewer, 2, 10)
            ->andReturn($list);
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock) use ($list): void {
        $key = CacheKeys::projectList(17, 501, 2, 10);

        $mock->shouldReceive('get')->once()->with($key)->andReturnNull();
        $mock->shouldReceive('put')->once()->with($key, $list, 120);
    });

    $query = new CachedGetProjectList(
        decorator: $databaseQuery,
        cache: projectListCacheRepository($taggedCache),
        logger: Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer, 2, 10))->toBe($list);
});

it('falls back to the database when creating the tagged cache fails', function (): void {
    [$workspace, $viewer] = projectListModels();
    $list                 = projectListPayload();

    $databaseQuery = Mockery::mock(GetProjectList::class, function (MockInterface $mock) use ($workspace, $viewer, $list): void {
        $mock->shouldReceive('execute')
            ->once()
            ->with($workspace, $viewer, 2, 10)
            ->andReturn($list);
    });
    $cache = Mockery::mock(Repository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('tags')
            ->once()
            ->andThrow(new RuntimeException('Tagged cache unavailable.'));
    });
    $logger = Mockery::mock(LoggerInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('warning')->once();
    });

    $query = new CachedGetProjectList(
        decorator: $databaseQuery,
        cache: $cache,
        logger: $logger,
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer, 2, 10))->toBe($list);
});

it('returns database data when storing the refreshed project list fails', function (): void {
    [$workspace, $viewer] = projectListModels();
    $list                 = projectListPayload();

    $databaseQuery = Mockery::mock(GetProjectList::class, function (MockInterface $mock) use ($workspace, $viewer, $list): void {
        $mock->shouldReceive('execute')
            ->once()
            ->with($workspace, $viewer, 2, 10)
            ->andReturn($list);
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock) use ($list): void {
        $key = CacheKeys::projectList(17, 501, 2, 10);

        $mock->shouldReceive('get')->once()->with($key)->andReturnNull();
        $mock->shouldReceive('put')
            ->once()
            ->with($key, $list, 120)
            ->andThrow(new RuntimeException('Redis unavailable.'));
    });
    $logger = Mockery::mock(LoggerInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('warning')->once();
    });

    $query = new CachedGetProjectList(
        decorator: $databaseQuery,
        cache: projectListCacheRepository($taggedCache),
        logger: $logger,
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer, 2, 10))->toBe($list);
});

it('falls back to the database when reading from project list cache fails', function (): void {
    [$workspace, $viewer] = projectListModels();
    $list                 = projectListPayload();

    $databaseQuery = Mockery::mock(GetProjectList::class, function (MockInterface $mock) use ($workspace, $viewer, $list): void {
        $mock->shouldReceive('execute')
            ->once()
            ->with($workspace, $viewer, 2, 10)
            ->andReturn($list);
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock): void {
        $mock->shouldReceive('get')->once()->andThrow(new RuntimeException('Redis unavailable.'));
    });
    $logger = Mockery::mock(LoggerInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('warning')
            ->once()
            ->with('Project list cache fallback.', [
                'workspaceId' => 17,
                'exception'   => RuntimeException::class,
            ]);
    });

    $query = new CachedGetProjectList(
        decorator: $databaseQuery,
        cache: projectListCacheRepository($taggedCache),
        logger: $logger,
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer, 2, 10))->toBe($list);
});

it('builds isolated project list keys', function (): void {
    expect(CacheKeys::projectList(17, 501, 1, 15))
        ->not->toBe(CacheKeys::projectList(18, 501, 1, 15))
        ->not->toBe(CacheKeys::projectList(17, 502, 1, 15))
        ->not->toBe(CacheKeys::projectList(17, 501, 2, 15))
        ->not->toBe(CacheKeys::projectList(17, 501, 1, 10));
});

/** @return array{Workspace, User} */
function projectListModels(): array
{
    return [
        (new Workspace)->forceFill(['id' => 17]),
        (new User)->forceFill(['id' => 501]),
    ];
}

/** @return array<string, mixed> */
function projectListPayload(): array
{
    return [
        'data' => [['id' => 91, 'name' => 'Cached project']],
        'meta' => [
            'current_page' => 2,
            'per_page'     => 10,
            'total'        => 11,
            'last_page'    => 2,
        ],
    ];
}

function projectListCacheRepository(TaggedCache $taggedCache): Repository
{
    return Mockery::mock(Repository::class, function (MockInterface $mock) use ($taggedCache): void {
        $mock->shouldReceive('tags')
            ->once()
            ->with([CacheKeys::projectTag(17)])
            ->andReturn($taggedCache);
    });
}
