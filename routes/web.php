<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Projects\AttachmentController;
use App\Http\Controllers\Projects\CommentController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectStatusController;
use App\Http\Controllers\Projects\ProjectViewController;
use App\Http\Controllers\Projects\SubtaskController;
use App\Http\Controllers\Projects\TaskController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::put('projects/{project}/statuses', [ProjectStatusController::class, 'update'])->name('projects.statuses.update');

        Route::post('projects/{project}/views', [ProjectViewController::class, 'store'])->name('projects.views.store');
        Route::get('projects/{project}/views/{view}', [ProjectViewController::class, 'show'])->name('projects.views.show');
        Route::patch('projects/{project}/views/{view}', [ProjectViewController::class, 'update'])->name('projects.views.update');
        Route::delete('projects/{project}/views/{view}', [ProjectViewController::class, 'destroy'])->name('projects.views.destroy');

        Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('projects.tasks.update');
        Route::delete('projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('projects.tasks.destroy');
        Route::post('projects/{project}/tasks/{task}/move', [TaskController::class, 'move'])->name('projects.tasks.move');
        Route::post('projects/{project}/tasks/{task}/accept', [TaskController::class, 'accept'])->name('projects.tasks.accept');

        Route::post('projects/{project}/tasks/{task}/subtasks', [SubtaskController::class, 'store'])->name('projects.tasks.subtasks.store');
        Route::patch('projects/{project}/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'update'])->name('projects.tasks.subtasks.update');
        Route::delete('projects/{project}/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy'])->name('projects.tasks.subtasks.destroy');

        Route::post('projects/{project}/tasks/{task}/attachments', [AttachmentController::class, 'store'])->name('projects.tasks.attachments.store');
        Route::get('projects/{project}/tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'download'])->name('projects.tasks.attachments.download');
        Route::get('projects/{project}/tasks/{task}/attachments/{attachment}/preview', [AttachmentController::class, 'preview'])->name('projects.tasks.attachments.preview');
        Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('projects.tasks.attachments.destroy');

        Route::post('projects/{project}/tasks/{task}/comments', [CommentController::class, 'store'])->name('projects.tasks.comments.store');
        Route::delete('projects/{project}/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->name('projects.tasks.comments.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::inertia('no-workspace', 'no-workspace')->name('no-workspace');
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
