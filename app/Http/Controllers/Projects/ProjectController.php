<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CreateProject;
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
     * Open the project's first view, passing along any ?task= to open.
     */
    public function show(Request $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        // Keep ?task= so links from emails open straight onto that task.
        return to_route('projects.views.show', [
            $current_team,
            $project,
            $project->views()->firstOrFail(),
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
