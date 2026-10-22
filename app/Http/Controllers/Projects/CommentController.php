<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Tasks\SendTaskNotifications;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Add a comment, or an internal note when the author is on the team.
     */
    public function store(Request $request, Team $current_team, Project $project, Task $task, SendTaskNotifications $notifications): RedirectResponse
    {
        Gate::authorize('view', $project);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => ($validated['is_internal'] ?? false) && ! $request->user()->isClientOf($current_team),
        ]);

        $notifications->commentPosted($comment);

        return back();
    }

    /**
     * Delete one of your own comments.
     */
    public function destroy(Request $request, Team $current_team, Project $project, Task $task, Comment $comment): RedirectResponse
    {
        Gate::authorize('view', $project);

        abort_unless($comment->user_id === $request->user()->id, 403);

        $comment->delete();

        return back();
    }
}
