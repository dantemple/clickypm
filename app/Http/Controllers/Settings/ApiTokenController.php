<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreateApiTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    /**
     * Show the user's personal access tokens. A token just made is shown
     * in full once, from the session; after that only its name remains.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('settings/api-tokens', [
            'tokens' => $request->user()->tokens()->latest('id')->get()->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'read_only' => ! $token->can('write'),
                'created_at_diff' => $token->created_at->diffForHumans(),
                'last_used_at_diff' => $token->last_used_at?->diffForHumans(),
                'expires_at' => $token->expires_at?->toDateString(),
                'expired' => $token->expires_at?->isPast() ?? false,
            ])->values()->all(),
            'newToken' => $request->session()->get('newToken'),
            'lifetimes' => CreateApiTokenRequest::LIFETIMES,
            'apiUrl' => url('/api/v1'),
        ]);
    }

    /**
     * Make a token and show it once.
     */
    public function store(CreateApiTokenRequest $request): RedirectResponse
    {
        $days = $request->validated('expires_in_days');

        $token = $request->user()->createToken(
            $request->validated('name'),
            $request->boolean('read_only') ? ['read'] : ['read', 'write'],
            $days ? now()->addDays((int) $days) : null,
        );

        return back()->with('newToken', $token->plainTextToken);
    }

    /**
     * Revoke one of the user's tokens. Anything still using it gets a 401
     * from then on.
     */
    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->firstOrFail()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token revoked.')]);

        return back();
    }
}
