<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can open the task: read its details, comments,
     * files and history, and add comments and files of their own.
     *
     * Clients only see requests awaiting triage when they raised them, so one
     * client never reads another's.
     */
    public function view(User $user, Task $task): bool
    {
        $project = $task->project;

        if (! $user->can('view', $project)) {
            return false;
        }

        if (! $task->needs_triage || ! $user->isClientOf($project->team)) {
            return true;
        }

        return $task->created_by === $user->id;
    }
}
