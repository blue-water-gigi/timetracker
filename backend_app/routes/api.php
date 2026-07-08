<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', fn (Request $request) => response()->json([
    'status' => 'ok',
    'app' => config('app.version'),
    'database' => 'ok',
    'redis' => 'ok',
    'queue' => 'ok',
    'storage' => 'ok',
], 201));

Route::middleware('auth:sanctum')->group(function () {});

Route::middleware('guest')->group(function () {
    Route::apiResources([
        'register' => RegistrationController::class,
        'login' => LoginController::class,
    ]);
});
