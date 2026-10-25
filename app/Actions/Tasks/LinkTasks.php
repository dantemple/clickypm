<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Validation\ValidationException;

/**
 * Makes one task wait for another. Links stay inside a project, a pair is
 * only linked once in either direction, and a link can't make a loop.
 */
class LinkTasks
{
    public function handle(Task $successor, Task $predecessor, int $lagDays = 0): TaskDependency
    {
        if ($successor->is($predecessor)) {
            $this->fail(__('A task can’t depend on itself.'));
        }

        if ($successor->project_id !== $predecessor->project_id) {
            $this->fail(__('Tasks can only depend on tasks in the same project.'));
        }

        $linked = TaskDependency::query()
            ->where(fn ($query) => $query->where('predecessor_id', $predecessor->id)->where('successor_id', $successor->id))
            ->orWhere(fn ($query) => $query->where('predecessor_id', $successor->id)->where('successor_id', $predecessor->id))
            ->exists();

        if ($linked) {
            $this->fail(__('Those tasks are already linked.'));
        }

        // Following existing links forward from the successor must never
        // arrive back at the predecessor, or the new link closes a loop.
        if ($this->reaches($successor, $predecessor)) {
            $this->fail(__('That would make the tasks depend on each other in a loop.'));
        }

        $dependency = TaskDependency::create([
            'predecessor_id' => $predecessor->id,
            'successor_id' => $successor->id,
            'type' => TaskDependency::FINISH_TO_START,
            'lag_days' => $lagDays,
        ]);

        $successor->recordActivity('blocked_by_added', ['title' => $predecessor->title]);
        $predecessor->recordActivity('blocks_added', ['title' => $successor->title]);

        return $dependency;
    }

    /**
     * Remove a link and note it on both tasks.
     */
    public function unlink(TaskDependency $dependency): void
    {
        $successor = $dependency->successor;
        $predecessor = $dependency->predecessor;

        $dependency->delete();

        $successor->recordActivity('blocked_by_removed', ['title' => $predecessor->title]);
        $predecessor->recordActivity('blocks_removed', ['title' => $successor->title]);
    }

    /**
     * Whether following links forward from the start ever arrives at the target.
     */
    private function reaches(Task $start, Task $target): bool
    {
        $frontier = [$start->id];
        $seen = [];

        while ($frontier !== []) {
            $next = TaskDependency::query()
                ->whereIn('predecessor_id', $frontier)
                ->pluck('successor_id')
                ->reject(fn (int $id) => isset($seen[$id]))
                ->all();

            if (in_array($target->id, $next, true)) {
                return true;
            }

            foreach ($next as $id) {
                $seen[$id] = true;
            }

            $frontier = $next;
        }

        return false;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['predecessor_id' => $message]);
    }
}
