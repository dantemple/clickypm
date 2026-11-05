<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamFeature;
use App\Http\Controllers\Controller;
use App\Models\GitHubInstallation;
use App\Models\Team;
use App\Support\GitHubApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamGitHubController extends Controller
{
    /**
     * Send a workspace owner to GitHub to install the app. The state ties
     * the installation GitHub sends back to this workspace.
     */
    public function connect(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);
        abort_unless($team->hasFeature(TeamFeature::GitHub) && GitHubApp::configured(), 404);

        $state = Str::random(40);

        $request->session()->put('github.setup', ['state' => $state, 'team_id' => $team->id]);

        return redirect()->away(GitHubApp::installUrl($state));
    }

    /**
     * Where GitHub sends people once the app is installed (or its
     * repositories changed). The installation's details arrive by webhook,
     * possibly before this; either way it's claimed for the workspace here.
     */
    public function setup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'installation_id' => ['required', 'integer', 'min:1'],
            'setup_action' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:64'],
        ]);

        $setup = $request->session()->pull('github.setup');
        $team = is_array($setup) && isset($setup['state']) && hash_equals($setup['state'], (string) ($validated['state'] ?? ''))
            ? Team::whereKey((int) $setup['team_id'])->first()
            : null;

        if (! $team || ! $request->user()->can('update', $team) || ! $team->hasFeature(TeamFeature::GitHub)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('That GitHub set-up link has expired. Start again from the workspace settings.')]);

            return to_route('teams.index');
        }

        if (($validated['setup_action'] ?? null) === 'request') {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Requested. Once an owner of the GitHub organisation approves the app, come back and connect it.')]);

            return to_route('teams.edit', $team);
        }

        $installation = GitHubInstallation::firstOrNew(['installation_id' => $validated['installation_id']]);

        if ($installation->team_id !== null && $installation->team_id !== $team->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('That GitHub account is already connected to another workspace.')]);

            return to_route('teams.edit', $team);
        }

        // One installation per workspace: a second one replaces the first.
        $team->githubInstallation()->whereKeyNot($installation->id ?? 0)->delete();

        $installation->fill(['team_id' => $team->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $installation->account_login
            ? __('GitHub connected to :account.', ['account' => $installation->account_login])
            : __('GitHub connected. Its repositories will appear in a moment.')]);

        return to_route('teams.edit', $team);
    }

    /**
     * Forget the installation. Links already on tasks stay; the app itself
     * is uninstalled on GitHub.
     */
    public function destroy(Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->githubInstallation()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('GitHub disconnected. Uninstall the app on GitHub too if you no longer want it there.')]);

        return back();
    }
}
