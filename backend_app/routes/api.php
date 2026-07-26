<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AdminRegistrationController;
use App\Http\Controllers\Auth\EmployeeRegistrationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectMember\ProjectMemberController;
use App\Http\Controllers\Timesheet\TimeEntryController;
use App\Http\Controllers\Timesheet\TimesheetController;
use App\Http\Controllers\Workspace\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.version'),
]));

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::delete('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('me', [LoginController::class, 'me'])->name('me');

    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('organizations/{organization}/workspaces', WorkspaceController::class)
        ->scoped();

    Route::post('organizations/{organization}/workspaces/{workspace}/rotate-join-code', [WorkspaceController::class, 'rotateJoinCode'])
        ->scopeBindings()
        ->middleware('throttle:rotateJoinCode');

    Route::apiResource('workspaces/{workspace}/projects', ProjectController::class)
        ->scoped();
    Route::get('workspaces/{workspace}/my-projects', [ProjectController::class, 'showMyProjects']);

    Route::apiResource('workspaces/{workspace}/projects/{project}/members', ProjectMemberController::class)
        ->parameters(['members' => 'membership'])
        ->scoped();

    Route::apiResource('workspaces/{workspace}/projects/{project}/timesheets', TimesheetController::class)
        ->scoped()
        ->except(['destroy']);

    Route::group(['controller' => TimesheetController::class], function () {
        Route::post('workspaces/{workspace}/projects/{project}/timesheets/{timesheet}/submit', 'submit');
        Route::post('workspaces/{workspace}/projects/{project}/timesheets/{timesheet}/approve', 'approve');
        Route::post('workspaces/{workspace}/projects/{project}/timesheets/{timesheet}/reject', 'reject');
    })->scopeBindings();

    Route::apiResource('workspaces/{workspace}/projects/{project}/timesheets/{timesheet}/entries', TimeEntryController::class)
        ->scoped()
        ->except(['show', 'index']);
});

Route::middleware('guest.api')->group(function () {
    Route::post('login', [LoginController::class, 'store'])->name('login')->middleware('throttle:login');

    Route::middleware(['throttle:register'])->group(function () {
        Route::post('register/employee', EmployeeRegistrationController::class)->name('register.employee');
        Route::post('register/admin', AdminRegistrationController::class)->name('register.admin');
    });
});
