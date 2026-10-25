<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /**
     * Serve a profile photo from a local disk. Any signed-in user may see
     * any other user's photo, but only by its current hashed file name, so
     * stale links die when the photo changes and names can't be guessed.
     */
    public function show(User $user, string $file): StreamedResponse|RedirectResponse
    {
        abort_unless($user->avatar_path && basename($user->avatar_path) === $file, 404);

        if (config("filesystems.disks.{$user->avatarDisk()}.driver") === 's3') {
            return redirect()->away($user->avatarUrl());
        }

        $disk = Storage::disk($user->avatarDisk());

        abort_unless($disk->exists($user->avatar_path), 404);

        // The name changes with the file, so the response can be cached hard.
        return $disk->response($user->avatar_path, $file, [
            'Content-Type' => $disk->mimeType($user->avatar_path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
