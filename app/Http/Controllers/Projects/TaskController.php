<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Tasks\SendTaskNotifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TaskController extends Controller
{
    /**
     * Add a task to the end of the project.
     *
     * Clients can only suggest a title and description; their tasks wait in
     * the project's request inbox until someone on the team triages them.
     */
    public function store(SaveTaskRequest $request, Team $current_team, Project $project, SendTaskNotifications $notifications): RedirectResponse
    {
        Gate::authorize('view', $project);

        $isClient = $request->user()->isClientOf($current_team);

        $fields = $request->safe()->except('attachments');

        $task = $project->tasks()->create([
            'status_id' => $project->defaultStatus()->id,
            ...($isClient ? Arr::only($fields, ['title', 'description']) : $fields),
            'needs_triage' => $isClient,
            // Count trashed tasks so a restored one never shares a position.
            'position' => (int) $project->tasks()->withTrashed()->max('position') + 1,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            Attachment::store($task, $file, $request->user());
        }

        if ($isClient) {
            $notifications->clientRequestCreated($task);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Request sent. The team will review it shortly.')]);
        }

        return back();
    }

    /**
     * Update one or more of a task's fields.
     */
    public function update(SaveTaskRequest $request, Team $current_team, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $project);

        $task->update($request->safe()->except('attachments'));

        return back();
    }

    /**
     * Drop a task into a status column at a given spot (the Board view).
     *
     * `order` is the destination column's task ids from top to bottom,
     * including the moved task. Those tasks swap their existing position
     * numbers around, so the order also holds in the List and Table views.
     */
    public function move(Request $request, Team $current_team, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)],
            'order' => ['required', 'array', 'max:1000', Rule::contains([$task->id])],
            'order.*' => ['integer', 'distinct', Rule::exists('tasks', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
        ]);

        DB::transaction(function () use ($project, $task, $validated) {
            $tasks = $project->tasks()->whereKey($validated['order'])->lockForUpdate()->get()->keyBy('id');
            $positions = $tasks->pluck('position')->sort()->values();
            $previous = null;

            foreach (array_values($validated['order']) as $index => $id) {
                // Keep positions strictly increasing even if some were tied.
                $position = $previous === null ? $positions[$index] : max($positions[$index], $previous + 1);
                $previous = $position;

                $moving = $tasks[$id];
                $moving->position = $position;

                if ($moving->is($task)) {
                    $moving->status_id = $validated['status_id'];
                }

                $moving->save();
            }
        });

        return back();
    }

    /**
     * Accept a client request onto the project's normal views.
     */
    public function accept(Team $current_team, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $project);

        $task->update(['needs_triage' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request accepted.')]);

        return back();
    }

    /**
     * Move a task (or a declined client request) to the trash.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $project);

        $task->moveToTrash($request->user());

        return back();
    }
}
