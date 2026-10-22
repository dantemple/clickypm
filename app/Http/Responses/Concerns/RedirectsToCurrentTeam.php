<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        // Invited clients have no personal workspace, so they may have none left.
        if (! $team) {
            return route('no-workspace', absolute: false);
        }

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }

    protected function currentTeam(Request $request): ?Team
    {
        $user = $request->user();

        abort_if(! $user, 403);

        return $user->currentTeam ?? $user->personalTeam() ?? $user->fallbackTeam();
    }
}
