<?php

namespace App\Actions\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Arr;

class CreateTask
{
    public function __construct(
        protected SaveCustomFieldValues $customFields,
        protected SyncTaskTags $tags,
        protected SendTaskNotifications $notifications,
    ) {}

    /**
     * Add a task to the end of the project and tell whoever it's assigned to.
     *
     * Clients can only suggest a title and description; their tasks wait in
     * the project's request inbox until someone on the team triages them.
     *
     * @param  array<string, mixed>  $fields  Validated task columns.
     * @param  array<int|string, mixed>  $customFields  Values keyed by field id.
     * @param  array<int, int>|null  $tagIds
     * @param  array<int, string>  $newTags
     */
    public function handle(User $user, Project $project, array $fields, array $customFields = [], ?array $tagIds = null, array $newTags = []): Task
    {
        $isClient = $user->isClientOf($project->team);

        $task = $project->tasks()->create([
            'status_id' => $project->defaultStatus()->id,
            ...($isClient ? Arr::only($fields, ['title', 'description']) : $fields),
            'needs_triage' => $isClient,
            // Count trashed tasks so a restored one never shares a position.
            'position' => (int) $project->tasks()->withTrashed()->max('position') + 1,
            'created_by' => $user->id,
        ]);

        if (! $isClient) {
            $this->customFields->handle($task, $customFields);
            $this->tags->handle($task, $tagIds, $newTags);
        }

        if ($task->assignee_id) {
            $this->notifications->taskAssigned($task, $user);
        }

        if ($isClient) {
            $this->notifications->clientRequestCreated($task);
        }

        return $task;
    }
}
