<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileAvatarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileAvatarController extends Controller
{
    /**
     * Upload a new profile photo, replacing any existing one.
     */
    public function store(ProfileAvatarRequest $request): RedirectResponse
    {
        $request->user()->updateAvatar($request->file('avatar'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Remove the profile photo and go back to initials.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->removeAvatar();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo removed.')]);

        return to_route('profile.edit');
    }
}
