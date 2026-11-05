<?php

namespace App\Actions\Tasks;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Collection;

class SyncCommentMentions
{
    /**
     * Record who the comment @-mentions, and say who's new.
     *
     * Only people who can actually open the task count: anyone else written
     * into the body stays as text. Internal notes can't mention clients,
     * since clients never see them.
     *
     * @return Collection<int, User> The people mentioned for the first time.
     */
    public function handle(Comment $comment): Collection
    {
        $task = $comment->task;
        $team = $task->project->team;

        $mentioned = User::query()
            ->whereKey(Comment::mentionedIds($comment->body))
            ->get()
            ->filter(fn (User $user) => $user->can('view', $task))
            ->reject(fn (User $user) => $comment->is_internal && $user->isClientOf($team))
            ->values();

        $previous = $comment->mentions()->pluck('users.id');

        $comment->mentions()->sync($mentioned->modelKeys());

        return $mentioned->reject(fn (User $user) => $previous->contains($user->id))->values();
    }
}
