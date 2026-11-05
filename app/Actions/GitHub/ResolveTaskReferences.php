<?php

namespace App\Actions\GitHub;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Support\TaskReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ResolveTaskReferences
{
    /**
     * The workspace's live tasks that the given references point at, with
     * their projects loaded. Unknown keys and numbers are skipped.
     *
     * @param  array<int, TaskReference>  $references
     * @return Collection<int, Task>
     */
    public function handle(Team $team, array $references): Collection
    {
        if ($references === []) {
            return new Collection;
        }

        $projects = $team->projects()->whereIn('key', array_map(fn (TaskReference $reference) => $reference->key, $references))->get()->keyBy('key');

        $tasks = Task::query()
            ->whereHas('project', fn (Builder $query) => $query->where('team_id', $team->id))
            ->where(function (Builder $query) use ($references, $projects) {
                foreach ($references as $reference) {
                    /** @var Project|null $project */
                    $project = $projects->get($reference->key);

                    if ($project) {
                        $query->orWhere(fn (Builder $query) => $query->where('project_id', $project->id)->where('number', $reference->number));
                    }
                }

                // Nothing matched a project: match nothing, rather than everything.
                $query->orWhereRaw('1 = 0');
            })
            ->orderBy('id')
            ->get();

        return $tasks->each(fn (Task $task) => $task->setRelation('project', $projects->firstWhere('id', $task->project_id)));
    }
}
