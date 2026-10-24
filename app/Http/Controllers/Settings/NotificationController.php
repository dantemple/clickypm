<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show the user's notification settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/notifications');
    }

    /**
     * Update which emails the user wants to receive.
     */
    public function update(NotificationSettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return to_route('notifications.edit');
    }
}
