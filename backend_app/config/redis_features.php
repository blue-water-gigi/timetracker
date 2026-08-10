<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Switch use case logic
|--------------------------------------------------------------------------
|
| Here are placed cache use cases for every need.
| 'enabled' are responsible for enabling use case.
| 'store' are represents which driver was used when operation happens.
| 'ttl_seconds' are represents global logic for each model collection
|
*/
return [
    'workspace_summary' => [
        'enabled' => env('REDIS_WORKSPACE_SUMMARY_ENABLED', false),
        'store'   => env('REDIS_WORKSPACE_SUMMARY_STORE', 'redis'),
        'ttl'     => (int) env('REDIS_WORKSPACE_SUMMARY_TTL', 120),
    ],
    'project_list' => [
        'enabled' => env('REDIS_PROJECT_LIST_ENABLED', false),
        'store'   => env('REDIS_PROJECT_LIST_STORE', 'redis'),
        'ttl'     => (int) env('REDIS_PROJECT_LIST_TTL', 120),
    ],
];
