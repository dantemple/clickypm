<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The in-app inbox behind the sidebar bell. Notifications belong to the
 * person, not the workspace, so the list shows everything they've been told
 * about and each entry's URL takes them to the right workspace.
 */
class NotificationController extends Controller
{
    /**
     * The latest notifications, newest first, for the bell's panel.
     */
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                ...$notification->data,
                'readAt' => $notification->read_at?->toIso8601String(),
                'createdAt' => $notification->created_at?->toIso8601String(),
                'createdAtDiff' => $notification->created_at?->diffForHumans(),
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    /**
     * Mark one notification read and go to whatever it was about.
     */
    public function read(Request $request, Team $current_team, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('dashboard', $current_team));
    }

    /**
     * Mark everything read.
     */
    public function readAll(Request $request, Team $current_team): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
