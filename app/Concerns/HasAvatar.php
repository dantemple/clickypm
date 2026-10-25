<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A profile photo stored on the attachments disk under a hashed name.
 * Avatars are read through the `avatar` accessor, which hands out a URL
 * rather than the storage path, so anything that serialises a user (the
 * shared `auth.user` prop, member lists) gets something an <img> can use.
 */
trait HasAvatar
{
    /**
     * Largest photo accepted, in kilobytes.
     */
    public const AVATAR_MAX_KILOBYTES = 2 * 1024;

    /**
     * Image types a photo may be. SVG is left out because it can carry scripts.
     */
    public const AVATAR_TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * How long a signed S3 link stays valid. Long enough to outlive a page
     * that stays open a while, short enough that a leaked link soon dies.
     */
    protected const AVATAR_URL_MINUTES = 60;

    /**
     * Boot the trait: deleting an account deletes its photo too.
     */
    public static function bootHasAvatar(): void
    {
        static::deleted(fn (User $user) => $user->deleteAvatarFile());
    }

    /**
     * Store a newly uploaded photo and drop the one it replaces.
     */
    public function updateAvatar(UploadedFile $file): void
    {
        $disk = $this->avatarDisk();
        $path = $file->store('avatars', $disk);

        // Disks don't throw by default; never point the column at nothing.
        throw_unless($path, RuntimeException::class, "Could not store the avatar on the {$disk} disk.");

        $previous = $this->avatar_path;

        $this->forceFill(['avatar_path' => $path])->save();

        if ($previous) {
            Storage::disk($disk)->delete($previous);
        }
    }

    /**
     * Remove the photo, going back to initials.
     */
    public function removeAvatar(): void
    {
        $this->deleteAvatarFile();

        $this->forceFill(['avatar_path' => null])->save();
    }

    /**
     * Delete the stored file, if there is one, without touching the column.
     */
    public function deleteAvatarFile(): void
    {
        if ($this->avatar_path) {
            Storage::disk($this->avatarDisk())->delete($this->avatar_path);
        }
    }

    /**
     * A URL for the photo, or null when the user hasn't set one. On S3 it's a
     * signed link straight to the bucket; otherwise it goes through
     * AvatarController, which checks the viewer is signed in.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn () => $this->avatarUrl());
    }

    /**
     * Work out the URL behind the `avatar` accessor.
     */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        $disk = $this->avatarDisk();

        if (config("filesystems.disks.{$disk}.driver") === 's3') {
            return Storage::disk($disk)->temporaryUrl($this->avatar_path, now()->addMinutes(self::AVATAR_URL_MINUTES));
        }

        // The hashed file name is in the URL so a new photo gets a new URL
        // and browsers don't keep showing a cached old one.
        return route('avatars.show', [$this, basename($this->avatar_path)]);
    }

    /**
     * The disk photos live on: the same one task attachments use.
     */
    public function avatarDisk(): string
    {
        return config('filesystems.attachments');
    }
}
