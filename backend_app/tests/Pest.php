<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    })
    ->in('Feature');

expect()->extend('toBeOne', fn () => $this->toBe(1));
