<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Attach one or more files to a task. Anyone who can see the project
     * can attach files, clients included.
     */
    public function store(Request $request, Team $current_team, Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);

        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES],
        ]);

        foreach ($request->file('files') as $file) {
            Attachment::store($task, $file, $request->user());
        }

        return back();
    }

    /**
     * Download a file under its original name. Files on S3 are fetched
     * straight from the bucket via a signed link; the link is only handed
     * out after the access check above, and expires after a few minutes.
     */
    public function download(Team $current_team, Project $project, Task $task, Attachment $attachment): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $task);

        if ($attachment->usesSignedUrls()) {
            return redirect()->away($attachment->signedUrl());
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->name);
    }

    /**
     * Show an image inline (for thumbnails). Non-image files are refused so
     * nothing uploaded can run in the browser as part of this site.
     */
    public function preview(Team $current_team, Project $project, Task $task, Attachment $attachment): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $task);

        abort_unless($attachment->isPreviewable(), 404);

        if ($attachment->usesSignedUrls()) {
            return redirect()->away($attachment->signedUrl(inline: true));
        }

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Remove a file. Uploaders can remove their own; the team can remove any.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Task $task, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('view', $task);

        abort_unless(
            $attachment->user_id === $request->user()->id || $request->user()->can('update', $project),
            403,
        );

        $attachment->delete();

        return back();
    }
}
