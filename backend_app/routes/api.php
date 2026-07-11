<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\TestController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy']);

    Route::get('/test', [TestController::class, 'index']);
});

Route::middleware('guest.api')->group(function () {
    Route::post('login', [LoginController::class, 'store']);
    Route::post('register', [RegistrationController::class, 'store']);
});
