<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ThemeColor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Show the user's appearance settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/appearance', [
            'themeColors' => ThemeColor::options(),
        ]);
    }

    /**
     * Save the sidebar colour on the account, so it follows the user
     * to every device.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme_color' => ['required', Rule::enum(ThemeColor::class)],
        ]);

        $request->user()->update($validated);

        return back();
    }
}
