<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\RateLimiterServiceProvider;

return [
    AppServiceProvider::class,
    RateLimiterServiceProvider::class,
];
