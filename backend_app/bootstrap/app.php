<?php

declare(strict_types=1);

use App\Exceptions\Domain\DuplicateTimesheetPeriodException;
use App\Exceptions\Domain\ProjectMembershipRequiredException;
use App\Exceptions\Domain\TimesheetStateConflict;
use App\Exceptions\Domain\TimesheetValidationException;
use App\Http\Middleware\EnsureGuest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleWithRedis();
        $middleware->statefulApi();
        $middleware->alias([
            'guest.api' => EnsureGuest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $th): bool => $request->is(['api/*']) || $request->expectsJson());

        $exceptions->render(function (TimesheetStateConflict $e, Request $request): ?JsonResponse {
            if (! $request->is(['api/*']) && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => [
                    'message' => $e->getMessage(),
                    'errorCode' => $e->errorCode(),
                    'context' => $e->context(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (TimesheetValidationException $e, Request $request): ?JsonResponse {
            if (! $request->is(['api/*']) && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => [
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'errorCode' => $e->errorCode(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (DuplicateTimesheetPeriodException $e, Request $request) {
            if (! $request->is(['api/*']) && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => [
                    'message' => $e->getMessage(),
                    'errorCode' => $e->errorCode(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (ProjectMembershipRequiredException $e, Request $request): ?JsonResponse {
            if (! $request->is(['api/*']) && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => [
                    'message' => $e->getMessage(),
                    'errorCode' => $e->errorCode(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request): ?JsonResponse {
            if (! $request->is(['api/*']) && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => [
                    'message' => $e->getMessage(),
                    'errorCode' => 'too_many_requests',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS, $e->getHeaders());
        });
    })->create();
