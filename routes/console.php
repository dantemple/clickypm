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

// Empty the trash: projects and tasks deleted more than 30 days ago go for good.
Schedule::command('model:prune', ['--model' => [Project::class, Task::class]])
    ->daily()
    ->description('Permanently delete projects and tasks that have been in the trash for 30 days');
