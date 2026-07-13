<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Workspace\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', fn(Request $request) => response()->json([
    'status' => 'ok',
    'app' => config('app.version'),
    'database' => 'ok',
    'redis' => 'ok',
    'queue' => 'ok',
    'storage' => 'ok',
], 201));

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('logout', [LoginController::class, 'destroy']);

    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('workspaces', WorkspaceController::class);
});

Route::middleware('guest.api')->group(function () {
    Route::post('login', [LoginController::class, 'store']);
    Route::post('register', [RegistrationController::class, 'store']);
});
