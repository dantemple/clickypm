<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * A file attached to a task. Files live on a private disk and are only
 * ever served through AttachmentController, which checks access first.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string|null $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Task $task
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'disk', 'path', 'name', 'mime_type', 'size'])]
class Attachment extends Model
{
    /**
     * Largest file accepted, in kilobytes. Keep below PHP's upload_max_filesize.
     */
    public const MAX_KILOBYTES = 10 * 1024;

    /**
     * Image types safe to show inline. Anything else (including SVG, which
     * can carry scripts) is only ever sent as a download.
     */
    public const PREVIEWABLE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::created(fn (Attachment $attachment) => $attachment->task->recordActivity('attachment_added', ['name' => $attachment->name]));

        static::deleted(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->task->recordActivity('attachment_removed', ['name' => $attachment->name]);
        });
    }

    /**
     * Store an uploaded file against a task.
     */
    public static function store(Task $task, UploadedFile $file, ?User $user): self
    {
        $disk = config('filesystems.attachments');
        $path = $file->store(self::directoryFor($task->project_id, $task->id), $disk);

        // Disks don't throw by default; never save a row pointing at nothing.
        throw_unless($path, RuntimeException::class, "Could not store {$file->getClientOriginalName()} on the {$disk} disk.");

        return $task->attachments()->create([
            'user_id' => $user?->id,
            'disk' => $disk,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Where a project's (or one task's) files live on disk, so a whole
     * project or task can be cleaned up in one go.
     */
    public static function directoryFor(int $projectId, ?int $taskId = null): string
    {
        return "attachments/{$projectId}".($taskId ? "/{$taskId}" : '');
    }

    /**
     * Whether to hand the file out via a short-lived signed link straight
     * from the storage service, rather than streaming it through the app.
     */
    public function usesSignedUrls(): bool
    {
        return config("filesystems.disks.{$this->disk}.driver") === 's3';
    }

    /**
     * A signed link to the file, valid for a few minutes, that the storage
     * service serves with the right name and disposition.
     */
    public function signedUrl(bool $inline = false): string
    {
        $fallback = str_replace(['/', '\\', '%'], '-', Str::ascii($this->name));

        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(5), [
            'ResponseContentType' => $inline ? $this->mime_type : 'application/octet-stream',
            'ResponseContentDisposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $this->name, $fallback),
        ]);
    }

    /**
     * Get the task the file is attached to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the person who uploaded it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the file can be shown inline as an image.
     */
    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_TYPES, true);
    }

    /**
     * Get the attachment as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toAttachmentData(): array
    {
        $route = [$this->task->project->team, $this->task->project, $this->task, $this];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'user' => $this->user?->only(['id', 'name']),
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => route('projects.tasks.attachments.download', $route),
            'preview_url' => $this->isPreviewable() ? route('projects.tasks.attachments.preview', $route) : null,
        ];
    }
}
