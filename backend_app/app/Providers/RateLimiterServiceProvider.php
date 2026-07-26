<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimiterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => $request->user()?->id
            ? Limit::perMinute(120)->by('user:'.$request->user()->id)
            : Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by('login:'.EmailNormalizer::normalize($request->string('email')->toString()).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => $request->routeIs(['register.admin'])
            ? Limit::perHour(5)->by('register-admin:'.$request->ip())
            : Limit::perHour(15)->by('register-employee:'.$request->ip()));

        RateLimiter::for('rotateJoinCode', fn (Request $request) => Limit::perDay(5)
            ->by('rotate-join-code:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
