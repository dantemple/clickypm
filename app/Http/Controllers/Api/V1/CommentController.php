<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\PostComment;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * The task's comments, oldest first. Clients don't see internal notes.
     */
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $isClient = $request->user()->isClientOf($task->project->team);

        return CommentResource::collection($task->comments()->visibleTo($isClient)->with('user:id,name')->get());
    }

    /**
     * Add a comment, or an internal note when the author is on the team.
     * Mention someone by writing @[Their Name](user:ID) in the body.
     */
    public function store(Request $request, Task $task, PostComment $postComment): JsonResponse
    {
        Gate::authorize('view', $task);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $comment = $postComment->handle($request->user(), $task, $validated['body'], (bool) ($validated['is_internal'] ?? false));

        return (new CommentResource($comment->load('user:id,name')))->response()->setStatusCode(201);
    }
}
