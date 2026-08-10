<?php

declare(strict_types=1);

use App\Contracts\Cache\WorkspaceCacheInvalidator;
use App\Events\WorkspaceReadModelChanged;
use App\Listeners\InvalidateWorkspaceReadCache;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

afterEach(function (): void {
    Mockery::close();
});

it('invalidates the workspace carried by the event', function (): void {
    $invalidator = Mockery::mock(WorkspaceCacheInvalidator::class, function (MockInterface $mock): void {
        $mock->shouldReceive('invalidate')->once()->with(17);
    });
    $logger = Mockery::mock(LoggerInterface::class);

    $listener = new InvalidateWorkspaceReadCache($invalidator, $logger);

    $listener->handle(new WorkspaceReadModelChanged(17, 'project_created'));
});

it('logs an invalidation failure without rethrowing it', function (): void {
    $invalidator = Mockery::mock(WorkspaceCacheInvalidator::class, function (MockInterface $mock): void {
        $mock->shouldReceive('invalidate')
            ->once()
            ->with(17)
            ->andThrow(new RuntimeException('Redis unavailable.'));
    });
    $logger = Mockery::mock(LoggerInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('error')
            ->once()
            ->with('Workspace cache invalidation failed.', [
                'workspaceId' => 17,
                'reason'      => 'project_created',
                'exception'   => RuntimeException::class,
            ]);
    });

    $listener = new InvalidateWorkspaceReadCache($invalidator, $logger);

    expect(fn () => $listener->handle(new WorkspaceReadModelChanged(17, 'project_created')))
        ->not->toThrow(RuntimeException::class);
});
