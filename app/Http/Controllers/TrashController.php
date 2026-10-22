<?php

namespace App\Http\Controllers;

use App\Enums\TeamPermission;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    /**
     * Show the workspace's trashed projects and tasks.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewTrash', $current_team);

        $purgeDate = fn (Project|Task $model) => $model->deleted_at?->addDays(Project::TRASH_DAYS)->toIso8601String();

        return Inertia::render('trash', [
            // Not 'projects': that name is the sidebar's shared prop.
            'trashedProjects' => Project::onlyTrashed()
                ->whereBelongsTo($current_team)
                ->with('deleter:id,name')
                ->withCount('tasks')
                ->latest('deleted_at')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'color' => $project->color,
                    'tasksCount' => $project->tasks_count,
                    'deletedBy' => $project->deleter?->name,
                    'deletedAt' => $project->deleted_at?->toIso8601String(),
                    'purgeAt' => $purgeDate($project),
                ]),
            // Tasks in a trashed project come back with the project, so they aren't listed separately.
            'trashedTasks' => Task::onlyTrashed()
                ->whereHas('project', fn ($query) => $query->whereBelongsTo($current_team))
                ->with(['project:id,name,color', 'deleter:id,name'])
                ->latest('deleted_at')
                ->limit(500)
                ->get()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'isRequest' => $task->needs_triage,
                    'project' => $task->project->only(['id', 'name', 'color']),
                    'deletedBy' => $task->deleter?->name,
                    'deletedAt' => $task->deleted_at?->toIso8601String(),
                    'purgeAt' => $purgeDate($task),
                ]),
            'canManageProjects' => $request->user()->hasTeamPermission($current_team, TeamPermission::UpdateTeam),
            'trashDays' => Project::TRASH_DAYS,
        ]);
    }

    /**
     * Bring a task back to its project.
     */
    public function restoreTask(Team $current_team, Task $task): RedirectResponse
    {
        $project = $this->projectFor($current_team, $task);

        Gate::authorize('update', $project);

        abort_if($project->trashed(), 422, __('Restore the project first.'));

        $task->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task restored.')]);

        return back();
    }

    /**
     * Delete a trashed task for good, along with its files.
     */
    public function destroyTask(Team $current_team, Task $task): RedirectResponse
    {
        Gate::authorize('update', $this->projectFor($current_team, $task));

        $task->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted for good.')]);

        return back();
    }

    /**
     * Bring a project back, with all of its tasks.
     */
    public function restoreProject(Team $current_team, Project $project): RedirectResponse
    {
        abort_unless($project->team_id === $current_team->id && $project->trashed(), 404);

        Gate::authorize('delete', $project);

        $project->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project restored.')]);

        return back();
    }

    /**
     * Delete a trashed project for good, with all of its tasks and files.
     */
    public function destroyProject(Team $current_team, Project $project): RedirectResponse
    {
        abort_unless($project->team_id === $current_team->id && $project->trashed(), 404);

        Gate::authorize('delete', $project);

        $project->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted for good.')]);

        return back();
    }

    /**
     * The (possibly trashed) project a trashed task belongs to, if it's in this workspace.
     */
    private function projectFor(Team $team, Task $task): Project
    {
        abort_unless($task->trashed(), 404);

        return Project::withTrashed()->whereKey($task->project_id)->whereBelongsTo($team)->firstOrFail();
    }
}
