<?php

declare(strict_types=1);

use App\Contracts\Cache\ProjectListCacheInvalidator;
use App\Contracts\Cache\WorkspaceCacheInvalidator;
use App\Contracts\Queries\GetProjectList;
use App\Contracts\Queries\GetWorkspaceSummary;
use App\Infrastructure\Cache\NullProjectListCacheInvalidator;
use App\Infrastructure\Cache\RedisProjectListCacheInvalidator;
use App\Providers\AppServiceProvider;
use App\Queries\CachedGetProjectList;
use App\Queries\EloquentGetProjectList;
use App\Queries\EloquentGetWorkspaceSummary;
use Tests\TestCase;

uses(TestCase::class);

it('keeps query and invalidator contracts resolvable when workspace cache is disabled', function (): void {
    config()->set('redis_features.workspace_summary.enabled', false);

    $this->app->offsetUnset(GetWorkspaceSummary::class);
    $this->app->offsetUnset(WorkspaceCacheInvalidator::class);

    new AppServiceProvider($this->app)->register();

    expect($this->app->make(GetWorkspaceSummary::class))
        ->toBeInstanceOf(EloquentGetWorkspaceSummary::class)
        ->and($this->app->bound(WorkspaceCacheInvalidator::class))
        ->toBeTrue();
});

it('keeps query and invalidator contracts resolvable when project list cache is disabled', function (): void {
    config()->set('redis_features.project_list.enabled', false);

    $this->app->offsetUnset(GetProjectList::class);
    $this->app->offsetUnset(ProjectListCacheInvalidator::class);

    (new AppServiceProvider($this->app))->register();

    expect($this->app->make(GetProjectList::class))
        ->toBeInstanceOf(EloquentGetProjectList::class)
        ->and($this->app->bound(ProjectListCacheInvalidator::class))
        ->toBeTrue()
        ->and($this->app->make(ProjectListCacheInvalidator::class))
        ->toBeInstanceOf(NullProjectListCacheInvalidator::class);
});

it('resolves cached query and Redis invalidator when project list cache is enabled', function (): void {
    config()->set('redis_features.project_list.enabled', true);
    config()->set('redis_features.project_list.store', 'redis');
    config()->set('redis_features.project_list.ttl', 120);

    $this->app->offsetUnset(GetProjectList::class);
    $this->app->offsetUnset(ProjectListCacheInvalidator::class);

    (new AppServiceProvider($this->app))->register();

    expect($this->app->make(GetProjectList::class))
        ->toBeInstanceOf(CachedGetProjectList::class)
        ->and($this->app->make(ProjectListCacheInvalidator::class))
        ->toBeInstanceOf(RedisProjectListCacheInvalidator::class);
});
