<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use App\Support\LiveUpdates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectCustomFieldController extends Controller
{
    /**
     * Choose which of the workspace's custom fields the project uses.
     *
     * Tasks keep their values when a field is switched off, so switching it
     * back on brings them back.
     */
    public function update(Request $request, Team $current_team, Project $project, LiveUpdates $liveUpdates): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'custom_field_ids' => ['present', 'array'],
            'custom_field_ids.*' => ['integer', 'distinct', Rule::exists('custom_fields', 'id')->where('team_id', $current_team->id)],
        ]);

        $project->customFields()->sync($validated['custom_field_ids']);

        $liveUpdates->projectChanged($project->id);

        return back();
    }
}
