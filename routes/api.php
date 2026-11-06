<?php

use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Every call carries a personal access token made under Settings > API
// tokens. Read-only tokens can't reach the write routes.
Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function () {
        Route::get('me', MeController::class)->name('me');

        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
        Route::get('workspaces/{workspace}/members', [WorkspaceController::class, 'members'])->name('workspaces.members');
        Route::get('workspaces/{workspace}/projects', [ProjectController::class, 'index'])->name('workspaces.projects.index');
        Route::get('workspaces/{workspace}/tasks/{key}', [TaskController::class, 'showByKey'])->name('workspaces.tasks.show');

        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');

        Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::get('tasks/{task}/comments', [CommentController::class, 'index'])->name('tasks.comments.index');

        Route::middleware('abilities:write')->group(function () {
            Route::post('workspaces/{workspace}/projects', [ProjectController::class, 'store'])->name('workspaces.projects.store');
            Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
            Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
            Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('tasks.comments.store');
        });
    });
