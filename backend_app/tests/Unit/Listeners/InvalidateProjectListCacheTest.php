<?php

declare(strict_types=1);

use App\Contracts\Cache\ProjectListCacheInvalidator;
use App\Events\ProjectListChanged;
use App\Listeners\InvalidateProjectListCache;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

afterEach(function (): void {
    Mockery::close();
});

it('invalidates the workspace carried by a project list event', function (): void {
    $invalidator = Mockery::mock(ProjectListCacheInvalidator::class, function (MockInterface $mock): void {
        $mock->shouldReceive('invalidate')->once()->with(17);
    });

    $listener = new InvalidateProjectListCache(
        Mockery::mock(LoggerInterface::class),
        $invalidator,
    );

    $listener->handle(new ProjectListChanged(17, 'project_created'));
});

it('logs an invalidation failure without rethrowing it', function (): void {
    $invalidator = Mockery::mock(ProjectListCacheInvalidator::class, function (MockInterface $mock): void {
        $mock->shouldReceive('invalidate')
            ->once()
            ->with(17)
            ->andThrow(new RuntimeException('Redis unavailable.'));
    });
    $logger = Mockery::mock(LoggerInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('error')->once();
    });

    $listener = new InvalidateProjectListCache($logger, $invalidator);

    expect(fn () => $listener->handle(new ProjectListChanged(17, 'project_created')))
        ->not->toThrow(RuntimeException::class);
});

it('dispatches project list changes only after a database commit', function (): void {
    expect(new ProjectListChanged(17, 'project_created'))
        ->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});
