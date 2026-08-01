<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Cache\WorkspaceCacheInvalidator;
use App\Contracts\Queries\GetWorkspaceSummary;
use App\Infrastructure\Cache\NullWorkspaceCacheInvalidator;
use App\Infrastructure\Cache\RedisWorkspaceCacheInvalidator;
use App\Queries\CachedGetWorkspaceSummary;
use App\Queries\EloquentGetWorkspaceSummary;
use Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(function (Application $app): GetWorkspaceSummary {
            $dbQuery = $app->make(EloquentGetWorkspaceSummary::class);

            if (! config('redis_features.workspace_summary.enabled')) {
                return $dbQuery;
            }

            return new CachedGetWorkspaceSummary(
                decorated: $dbQuery,
                cache: Cache::store(config('redis_features.workspace_summary.store')),
                logger: $app->make(LoggerInterface::class),
                ttl: config('redis_features.workspace_summary.ttl')
            );
        });

        $this->app->bind(fn(Application $app): WorkspaceCacheInvalidator => config('redis_features.workspace_summary.enabled')
            ? new RedisWorkspaceCacheInvalidator(
                Cache::store(config('redis_features.workspace_summary.store')),
            )
            : new NullWorkspaceCacheInvalidator);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
