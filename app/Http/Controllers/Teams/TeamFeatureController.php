<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamFeature;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamFeatureController extends Controller
{
    /**
     * Switch one of the workspace's optional modules on or off.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $validated = $request->validate([
            'feature' => ['required', 'string', 'in:'.implode(',', TeamFeature::values())],
            'enabled' => ['required', 'boolean'],
        ]);

        $feature = TeamFeature::from($validated['feature']);

        $team->update([
            'features' => [...($team->features ?? []), $feature->value => $validated['enabled']],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['enabled']
                ? __(':feature switched on.', ['feature' => $feature->label()])
                : __(':feature switched off.', ['feature' => $feature->label()]),
        ]);

        return back();
    }
}
