<?php

declare(strict_types=1);

use App\Contracts\Cache\WorkspaceCacheInvalidator;
use App\Contracts\Queries\GetWorkspaceSummary;
use App\Providers\AppServiceProvider;
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
