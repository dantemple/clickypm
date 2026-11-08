<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;

class UpdateTask
{
    public function __construct(
        protected SaveCustomFieldValues $customFields,
        protected SyncTaskTags $tags,
        protected SendTaskNotifications $notifications,
    ) {}

    /**
     * Change one or more of a task's fields and tell anyone newly assigned.
     *
     * @param  array<string, mixed>  $fields  Validated task columns.
     * @param  array<int|string, mixed>  $customFields  Values keyed by field id.
     * @param  array<int, int>|null  $tagIds
     * @param  array<int, string>  $newTags
     */
    public function handle(User $user, Task $task, array $fields, array $customFields = [], ?array $tagIds = null, array $newTags = []): Task
    {
        $task->update($fields);

        $this->customFields->handle($task, $customFields);
        $this->tags->handle($task, $tagIds, $newTags);

        if ($task->wasChanged('assignee_id') && $task->assignee_id) {
            $this->notifications->taskAssigned($task, $user);
        }

        return $task;
    }
}
