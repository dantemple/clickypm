<?php

namespace App\Actions\Tasks;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncTaskTags
{
    /**
     * Set the task's tags.
     *
     * `$tagIds` replaces the task's tags with those (null leaves them as they
     * are); `$newNames` are made in the project if they don't exist yet and
     * added on top. Each tag added or removed goes in the task's history,
     * and the task is touched so viewers see it live.
     *
     * @param  array<int, int>|null  $tagIds
     * @param  array<int, string>  $newNames
     */
    public function handle(Task $task, ?array $tagIds, array $newNames = []): void
    {
        if ($tagIds === null && $newNames === []) {
            return;
        }

        DB::transaction(function () use ($task, $tagIds, $newNames) {
            $project = $task->project;
            $current = $task->tags()->get()->keyBy('id');
            $target = $tagIds ?? $current->modelKeys();

            foreach ($newNames as $name) {
                $target[] = $this->findOrCreate($project, $name)->id;
            }

            $target = array_values(array_unique($target));
            $added = array_diff($target, $current->modelKeys());
            $removed = array_diff($current->modelKeys(), $target);

            if ($added === [] && $removed === []) {
                return;
            }

            $task->tags()->sync($target);
            $tags = $project->tags()->whereKey($added)->get();

            foreach ($tags as $tag) {
                $task->recordActivity('tagged', ['name' => $tag->name, 'color' => $tag->color]);
            }

            foreach ($removed as $id) {
                $task->recordActivity('untagged', ['name' => $current[$id]->name]);
            }

            $task->touch();
        });
    }

    /**
     * The project's tag of that name, made if there isn't one yet.
     */
    private function findOrCreate(Project $project, string $name): Tag
    {
        $name = trim($name);
        $existing = $project->tags()->named($name)->first();

        if ($existing) {
            return $existing;
        }

        if ($project->tags()->count() >= Tag::MAX_PER_PROJECT) {
            throw ValidationException::withMessages([
                'new_tags' => __('A project can have up to :max tags.', ['max' => Tag::MAX_PER_PROJECT]),
            ]);
        }

        return $project->tags()->create(['name' => $name, 'color' => Tag::nextColor($project)]);
    }
}
