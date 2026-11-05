<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TeamFeature;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectGitHubController extends Controller
{
    /**
     * Choose which of the workspace's repositories the project follows, and
     * where pull requests from them move its tasks.
     */
    public function update(Request $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_unless($current_team->hasFeature(TeamFeature::GitHub), 404);

        $installation = $current_team->githubInstallation;
        $status = ['nullable', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)];

        $validated = $request->validate([
            'repository_ids' => ['present', 'array', 'max:100'],
            'repository_ids.*' => ['integer', 'distinct', Rule::exists('github_repositories', 'id')->where('github_installation_id', $installation?->id)],
            'opened_status_id' => $status,
            'merged_status_id' => $status,
        ]);

        $project->githubRepositories()->sync($validated['repository_ids']);

        $project->forceFill([
            'github_opened_status_id' => $validated['opened_status_id'] ?? null,
            'github_merged_status_id' => $validated['merged_status_id'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('GitHub settings saved.')]);

        return back();
    }
}
