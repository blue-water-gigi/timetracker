<?php

declare(strict_types=1);

use App\Contracts\Queries\Statistics\GetPersonalStatistics;
use App\Contracts\Queries\Statistics\GetProjectStatistics;
use App\Contracts\Queries\Statistics\GetProjectTeamStatistics;
use App\Contracts\Queries\Statistics\GetWorkspaceStatistics;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Statistics\StatisticsPeriod;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) data_get(DB::selectOne('SELECT current_database() AS name'), 'name');

if ($database !== 'time_track_load') {
    throw new RuntimeException("EXPLAIN is allowed only on time_track_load; connected to {$database}.");
}

$workspace = Workspace::query()->findOrFail(1);
$project   = Project::query()
    ->where('workspace_id', $workspace->getKey())
    ->findOrFail(1);
$employee = User::query()
    ->where('email', 'statistics-load-user-001@example.test')
    ->firstOrFail();

$monthPeriod = StatisticsPeriod::fromValidated([
    'from'        => '2025-08-13',
    'to'          => '2026-08-13',
    'granularity' => 'month',
]);
$dayPeriod = StatisticsPeriod::fromValidated([
    'from'        => '2025-08-13',
    'to'          => '2026-08-13',
    'granularity' => 'day',
]);

$captured        = [];
$currentEndpoint = '';
$capture         = true;

DB::listen(function (QueryExecuted $query) use (&$capture, &$captured, &$currentEndpoint): void {
    if (! $capture) {
        return;
    }

    $captured[$currentEndpoint][] = [
        'sql'      => $query->sql,
        'bindings' => $query->bindings,
    ];
});

$endpoints = [
    'workspace' => fn () => app(GetWorkspaceStatistics::class)->execute($workspace, $monthPeriod),
    'personal'  => fn () => app(GetPersonalStatistics::class)->execute($workspace, $employee, $monthPeriod),
    'project'   => fn () => app(GetProjectStatistics::class)->execute($workspace, $project, $dayPeriod),
    'team'      => fn () => app(GetProjectTeamStatistics::class)->execute($workspace, $project, $monthPeriod),
];

foreach ($endpoints as $endpoint => $execute) {
    $currentEndpoint = $endpoint;
    $execute();
}

$capture          = false;
$resultsDirectory = __DIR__.'/results';
$manifest         = [];

foreach ($captured as $endpoint => $queries) {
    foreach ($queries as $index => $query) {
        $rows = DB::select(
            'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$query['sql'],
            $query['bindings'],
        );
        $rawPlan = $rows[0]->{'QUERY PLAN'} ?? null;

        if (! is_string($rawPlan)) {
            throw new RuntimeException("PostgreSQL did not return a JSON plan for {$endpoint} query {$index}.");
        }

        $plan     = json_decode($rawPlan, true, flags: JSON_THROW_ON_ERROR);
        $number   = $index + 1;
        $fileName = sprintf('explain-%s-%02d.json', $endpoint, $number);
        $document = [
            'endpoint'    => $endpoint,
            'queryNumber' => $number,
            'sql'         => $query['sql'],
            'bindings'    => $query['bindings'],
            'explain'     => $plan,
        ];

        file_put_contents(
            $resultsDirectory.'/'.$fileName,
            json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL,
        );

        $root       = $plan[0];
        $rootNode   = $root['Plan'];
        $manifest[] = [
            'endpoint'         => $endpoint,
            'queryNumber'      => $number,
            'file'             => $fileName,
            'executionTimeMs'  => $root['Execution Time'],
            'planningTimeMs'   => $root['Planning Time'],
            'rootNode'         => $rootNode['Node Type'],
            'sharedHitBlocks'  => $rootNode['Shared Hit Blocks']  ?? 0,
            'sharedReadBlocks' => $rootNode['Shared Read Blocks'] ?? 0,
        ];

        printf(
            "%s #%d: %.3f ms, %s, shared hits=%d, reads=%d\n",
            $endpoint,
            $number,
            $root['Execution Time'],
            $rootNode['Node Type'],
            $rootNode['Shared Hit Blocks']  ?? 0,
            $rootNode['Shared Read Blocks'] ?? 0,
        );
    }
}

file_put_contents(
    $resultsDirectory.'/explain-manifest.json',
    json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL,
);

printf("Saved %d plans to %s.\n", count($manifest), $resultsDirectory);
