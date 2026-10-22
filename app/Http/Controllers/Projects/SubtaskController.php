<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubtaskController extends Controller
{
    /**
     * Add a subtask to the end of the task's checklist.
     */
    public function store(Request $request, Team $current_team, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $project);

        $task->subtasks()->create([
            ...$this->validated($request, $project, required: true),
            'position' => (int) $task->subtasks()->max('position') + 1,
        ]);

        return back();
    }

    /**
     * Tick, untick, rename, assign or date a subtask.
     */
    public function update(Request $request, Team $current_team, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $this->validated($request, $project);

        if (array_key_exists('completed', $validated)) {
            $validated['completed_at'] = $validated['completed'] ? ($subtask->completed_at ?? now()) : null;
            unset($validated['completed']);
        }

        $subtask->update($validated);

        return back();
    }

    /**
     * Remove a subtask.
     */
    public function destroy(Team $current_team, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        Gate::authorize('update', $project);

        $subtask->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Project $project, bool $required = false): array
    {
        return $request->validate([
            'title' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            'completed' => ['sometimes', 'boolean'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('team_members', 'user_id')->where('team_id', $project->team_id)],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);
    }
}
