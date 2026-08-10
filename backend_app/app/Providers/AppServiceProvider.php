<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Cache\ProjectListCacheInvalidator;
use App\Contracts\Cache\WorkspaceCacheInvalidator;
use App\Contracts\Queries\GetProjectList;
use App\Contracts\Queries\GetWorkspaceSummary;
use App\Contracts\Queries\Statistics\GetPersonalStatistics;
use App\Contracts\Queries\Statistics\GetWorkspaceStatistics;
use App\Infrastructure\Cache\NullProjectListCacheInvalidator;
use App\Infrastructure\Cache\NullWorkspaceCacheInvalidator;
use App\Infrastructure\Cache\RedisProjectListCacheInvalidator;
use App\Infrastructure\Cache\RedisWorkspaceCacheInvalidator;
use App\Queries\CachedGetProjectList;
use App\Queries\CachedGetWorkspaceSummary;
use App\Queries\EloquentGetProjectList;
use App\Queries\EloquentGetWorkspaceSummary;
use App\Queries\Statistics\EloquentGetPersonalStatistics;
use App\Queries\Statistics\EloquentGetWorkspaceStatistics;
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

        $this->app->bind(function (Application $app): GetProjectList {
            $dbQuery = $app->make(EloquentGetProjectList::class);

            if (! config('redis_features.project_list.enabled')) {
                return $dbQuery;
            }

            return new CachedGetProjectList(
                decorator: $dbQuery,
                cache: Cache::store(config('redis_features.project_list.store')),
                logger: $app->make(LoggerInterface::class),
                ttl: config('redis_features.project_list.ttl')
            );
        });

        $this->app->bind(fn (Application $app): WorkspaceCacheInvalidator => config('redis_features.workspace_summary.enabled')
            ? new RedisWorkspaceCacheInvalidator(
                Cache::store(config('redis_features.workspace_summary.store')),
            )
            : new NullWorkspaceCacheInvalidator);

        $this->app->bind(fn (): ProjectListCacheInvalidator => config('redis_features.project_list.enabled')
            ? new RedisProjectListCacheInvalidator(
                Cache::store(config('redis_features.project_list.store')),
            )
            : new NullProjectListCacheInvalidator);

        $this->app->bind(GetPersonalStatistics::class, EloquentGetPersonalStatistics::class);
        $this->app->bind(GetWorkspaceStatistics::class, EloquentGetWorkspaceStatistics::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
