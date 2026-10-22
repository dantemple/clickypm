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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
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
            'position' => (int) $project->tasks()->max('position') + 1,
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
     * Delete a task (or decline a client request).
     */
    public function destroy(Team $current_team, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $project);

        $task->delete();

        return back();
    }
}
