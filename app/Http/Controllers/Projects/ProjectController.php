<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\MoveProject;
use App\Enums\ViewType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\SaveProjectRequest;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * Create a project in the workspace and open its first view.
     */
    public function store(SaveProjectRequest $request, Team $current_team, CreateProject $createProject): RedirectResponse
    {
        Gate::authorize('create', [Project::class, $current_team]);

        $project = $createProject->handle(
            $request->user(),
            $current_team,
            $request->validated('name'),
            $request->validated('color'),
            $request->validated('description'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.views.show', [$current_team, $project, $project->views()->first()]);
    }

    /**
     * Open the project on its List view, passing along any ?task= to open.
     */
    public function show(Request $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        // Open on a List view, whatever order the tabs are in; fall back to
        // the first tab if the project's List views have all been removed.
        $view = $project->views()->where('type', ViewType::List)->first()
            ?? $project->views()->firstOrFail();

        // Keep ?task= so links from emails open straight onto that task.
        return to_route('projects.views.show', [
            $current_team,
            $project,
            $view,
            ...$request->only('task'),
        ]);
    }

    /**
     * Update the project's details.
     */
    public function update(SaveProjectRequest $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        return back();
    }

    /**
     * Put a finished project out of the way. It stays readable and keeps
     * everything, but leaves the sidebar, dashboard, timeline, search and
     * reminders until it's unarchived.
     */
    public function archive(Request $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->forceFill(['archived_at' => now(), 'archived_by' => $request->user()->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project archived.')]);

        return to_route('dashboard', $current_team);
    }

    /**
     * Bring an archived project back into use.
     */
    public function unarchive(Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->forceFill(['archived_at' => null, 'archived_by' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project unarchived.')]);

        return back();
    }

    /**
     * Move the project, with everything in it, to another of the user's
     * workspaces, and follow it there.
     */
    public function move(Request $request, Team $current_team, Project $project, MoveProject $moveProject): RedirectResponse
    {
        $validated = $request->validate(['team' => ['required', 'string', 'exists:teams,slug']]);

        $destination = Team::where('slug', $validated['team'])->firstOrFail();

        Gate::authorize('move', [$project, $destination]);

        $moveProject->handle($project, $destination);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project moved to :workspace.', ['workspace' => $destination->name])]);

        return to_route('projects.show', [$destination, $project]);
    }

    /**
     * Move the project, with everything in it, to the trash.
     */
    public function destroy(Request $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->moveToTrash($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project moved to the trash.')]);

        return to_route('dashboard', $current_team);
    }
}
