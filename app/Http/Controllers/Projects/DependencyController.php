<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Tasks\LinkTasks;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DependencyController extends Controller
{
    /**
     * Make the task wait for another task in the project.
     */
    public function store(Request $request, Team $current_team, Project $project, Task $task, LinkTasks $link): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'predecessor_id' => ['required', 'integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
            'lag_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ]);

        $link->handle($task, $project->tasks()->whereKey($validated['predecessor_id'])->firstOrFail(), $validated['lag_days'] ?? 0);

        return back();
    }

    /**
     * Remove a link between two of the project's tasks.
     */
    public function destroy(Team $current_team, Project $project, TaskDependency $dependency, LinkTasks $link): RedirectResponse
    {
        Gate::authorize('update', $project);

        abort_unless($dependency->successor->project_id === $project->id, 404);

        $link->unlink($dependency);

        return back();
    }
}
