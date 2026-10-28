<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\SyncProjectTags;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectTagController extends Controller
{
    /**
     * Replace the project's tag list: rename, recolour, add or delete tags.
     */
    public function update(Request $request, Team $current_team, Project $project, SyncProjectTags $sync): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'tags' => ['present', 'array', 'max:'.Tag::MAX_PER_PROJECT],
            'tags.*.id' => ['nullable', 'integer'],
            'tags.*.name' => ['required', 'string', 'max:'.Tag::MAX_NAME_LENGTH, 'distinct:ignore_case'],
            'tags.*.color' => ['required', 'hex_color'],
        ]);

        $sync->handle($project, $validated['tags']);

        return back();
    }
}
