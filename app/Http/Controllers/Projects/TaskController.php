<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;
use Inertia\Inertia;

class TaskController extends Controller
{
    /**
     * Add a task to the end of the project.
     *
     * Clients can only suggest a title and description; their tasks wait in
     * the project's request inbox until someone on the team triages them.
     */
    public function store(SaveTaskRequest $request, Team $current_team, Project $project, CreateTask $createTask): RedirectResponse
    {
        Gate::authorize('view', $project);

        $task = $createTask->handle(
            $request->user(),
            $project,
            $request->safe()->except(['attachments', 'custom_fields', 'tag_ids', 'new_tags']),
            $request->validated('custom_fields', []),
            $request->validated('tag_ids'),
            $request->validated('new_tags', []),
        );

        foreach ($request->file('attachments', []) as $file) {
            Attachment::store($task, $file, $request->user());
        }

        if ($task->needs_triage) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Request sent. The team will review it shortly.')]);
        }

        return back();
    }

    /**
     * Update one or more of a task's fields.
     */
    public function update(SaveTaskRequest $request, Team $current_team, Project $project, Task $task, UpdateTask $updateTask): RedirectResponse
    {
        Gate::authorize('update', $project);

        $updateTask->handle(
            $request->user(),
            $task,
            $request->safe()->except(['attachments', 'custom_fields', 'tag_ids', 'new_tags']),
            $request->validated('custom_fields', []),
            $request->validated('tag_ids'),
            $request->validated('new_tags', []),
        );

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

        $validated = Validator::make($request->all(), [
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)],
            'order' => ['required', 'array', 'max:1000', Rule::contains([$task->id])],
            'order.*' => ['integer', 'distinct'],
        ])->after(function (ValidatorContract $validator) use ($project) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Every id must be one of the project's live tasks. One query for
            // the whole list, rather than an "exists" rule's query per id.
            $order = $validator->getData()['order'];
            $known = $project->tasks()->whereKey($order)->pluck('id')->flip();

            foreach ($order as $index => $id) {
                if (! $known->has((int) $id)) {
                    $validator->errors()->add("order.{$index}", __('validation.exists', ['attribute' => "order.{$index}"]));
                }
            }
        })->validate();

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
