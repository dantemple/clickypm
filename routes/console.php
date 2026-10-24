<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

// Each morning, email everyone their open tasks that are due today or overdue.
Schedule::command('tasks:send-due-reminders')
    ->dailyAt('08:00')
    ->onOneServer()
    ->description('Email everyone their tasks that are due today or overdue');

// Empty the trash: projects and tasks deleted more than 30 days ago go for good.
Schedule::command('model:prune', ['--model' => [Project::class, Task::class]])
    ->daily()
    ->description('Permanently delete projects and tasks that have been in the trash for 30 days');
