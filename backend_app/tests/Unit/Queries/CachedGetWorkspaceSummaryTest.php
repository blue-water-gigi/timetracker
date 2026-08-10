<?php

declare(strict_types=1);

use App\Contracts\Queries\GetWorkspaceSummary;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\CachedGetWorkspaceSummary;
use App\Support\Cache\CacheKeys;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

afterEach(function (): void {
    Mockery::close();
});

it('returns a cached workspace summary without executing the database query', function (): void {
    [$workspace, $viewer] = workspaceSummaryModels();
    $summary              = workspaceSummaryPayload();

    $databaseQuery = Mockery::mock(GetWorkspaceSummary::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('execute');
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock) use ($summary): void {
        $mock->shouldReceive('get')
            ->once()
            ->with(CacheKeys::workspaceSummary(17, 501))
            ->andReturn($summary);
    });
    $cache = workspaceSummaryCacheRepository($taggedCache);

    $query = new CachedGetWorkspaceSummary(
        decorated: $databaseQuery,
        cache: $cache,
        logger: Mockery::mock(LoggerInterface::class),
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer))->toBe($summary);
});

it('loads and stores a workspace summary on a cache miss', function (): void {
    [$workspace, $viewer] = workspaceSummaryModels();
    $summary              = workspaceSummaryPayload();

    $databaseQuery = Mockery::mock(GetWorkspaceSummary::class, function (MockInterface $mock) use ($workspace, $viewer, $summary): void {
        $mock->shouldReceive('execute')
            ->once()
            ->with($workspace, $viewer)
            ->andReturn($summary);
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock) use ($summary): void {
        $key = CacheKeys::workspaceSummary(17, 501);

        $mock->shouldReceive('get')->once()->with($key)->andReturnNull();
        $mock->shouldReceive('put')->once()->with($key, $summary, 120);
    });
    $cache = workspaceSummaryCacheRepository($taggedCache);

    $query = new CachedGetWorkspaceSummary(
        decorated: $databaseQuery,
        cache: $cache,
        logger: Mockery::mock(LoggerInterface::class),
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer))->toBe($summary);
});

it('falls back to the database when reading from cache fails', function (): void {
    [$workspace, $viewer] = workspaceSummaryModels();
    $summary              = workspaceSummaryPayload();

    $databaseQuery = Mockery::mock(GetWorkspaceSummary::class, function (MockInterface $mock) use ($workspace, $viewer, $summary): void {
        $mock->shouldReceive('execute')
            ->once()
            ->with($workspace, $viewer)
            ->andReturn($summary);
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock): void {
        $mock->shouldReceive('get')->once()->andThrow(new RuntimeException('Redis unavailable.'));
    });
    $logger = Mockery::mock(LoggerInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('warning')
            ->once()
            ->with('Workspace summary cache fallback.', [
                'workspaceId' => 17,
                'exception'   => RuntimeException::class,
            ]);
    });

    $query = new CachedGetWorkspaceSummary(
        decorated: $databaseQuery,
        cache: workspaceSummaryCacheRepository($taggedCache),
        logger: $logger,
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer))->toBe($summary);
});

it('returns database data when storing the refreshed summary fails', function (): void {
    [$workspace, $viewer] = workspaceSummaryModels();
    $summary              = workspaceSummaryPayload();

    $databaseQuery = Mockery::mock(GetWorkspaceSummary::class, function (MockInterface $mock) use ($summary): void {
        $mock->shouldReceive('execute')->once()->andReturn($summary);
    });
    $taggedCache = Mockery::mock(TaggedCache::class, function (MockInterface $mock): void {
        $mock->shouldReceive('get')->once()->andReturnNull();
        $mock->shouldReceive('put')->once()->andThrow(new RuntimeException('Redis unavailable.'));
    });
    $logger = Mockery::mock(LoggerInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('warning')->once();
    });

    $query = new CachedGetWorkspaceSummary(
        decorated: $databaseQuery,
        cache: workspaceSummaryCacheRepository($taggedCache),
        logger: $logger,
        ttl: 120,
    );

    expect($query->execute($workspace, $viewer))->toBe($summary);
});

it('builds isolated keys for different workspaces and viewers', function (): void {
    expect(CacheKeys::workspaceSummary(17, 501))
        ->not->toBe(CacheKeys::workspaceSummary(18, 501))
        ->not->toBe(CacheKeys::workspaceSummary(17, 502));
});

/** @return array{Workspace, User} */
function workspaceSummaryModels(): array
{
    $workspace = (new Workspace)->forceFill(['id' => 17]);
    $viewer    = (new User)->forceFill(['id' => 501]);

    return [$workspace, $viewer];
}

/** @return array<string, int> */
function workspaceSummaryPayload(): array
{
    return [
        'workspace_id'           => 17,
        'projects_count'         => 2,
        'members_count'          => 3,
        'valid_timesheets_count' => 4,
    ];
}

function workspaceSummaryCacheRepository(TaggedCache $taggedCache): Repository
{
    return Mockery::mock(Repository::class, function (MockInterface $mock) use ($taggedCache): void {
        $mock->shouldReceive('tags')
            ->once()
            ->with([CacheKeys::workspaceTag(17)])
            ->andReturn($taggedCache);
    });
}
