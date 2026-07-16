<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AdminRegistrationController;
use App\Http\Controllers\Auth\EmployeeRegistrationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectMember\ProjectMemberController;
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
    Route::delete('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('organizations/{organization}/workspaces', WorkspaceController::class)
        ->scoped();
    Route::post('organizations/{organization}/workspaces/{workspace}/rotate-join-code', [WorkspaceController::class, 'rotateJoinCode'])
        ->scopeBindings();

    Route::apiResource('workspaces/{workspace}/projects', ProjectController::class)
        ->scoped();

    Route::apiResource('workspaces/{workspace}/projects/{project}/members', ProjectMemberController::class)
        ->parameters(['members' => 'membership'])
        ->scoped();
});

Route::middleware('guest.api')->group(function () {
    Route::post('login', [LoginController::class, 'store'])->name('login');

    Route::post('register/employee', EmployeeRegistrationController::class)->name('register.employee');
    Route::post('register/admin', AdminRegistrationController::class)->name('register.admin');
});
