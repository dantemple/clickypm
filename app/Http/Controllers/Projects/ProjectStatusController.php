<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\SyncProjectStatuses;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectStatusController extends Controller
{
    /**
     * Replace the project's status list.
     */
    public function update(Request $request, Team $current_team, Project $project, SyncProjectStatuses $sync): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'statuses' => ['required', 'array', 'min:1', 'max:20'],
            'statuses.*.id' => ['nullable', 'integer'],
            'statuses.*.name' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'statuses.*.color' => ['required', 'hex_color'],
            'statuses.*.is_done' => ['required', 'boolean'],
        ]);

        $sync->handle($project, $validated['statuses']);

        return back();
    }
}
