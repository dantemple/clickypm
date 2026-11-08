<?php

namespace App\Actions\Tasks;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class PostComment
{
    public function __construct(
        protected SyncCommentMentions $mentions,
        protected SendTaskNotifications $notifications,
    ) {}

    /**
     * Add a comment, or an internal note when the author is on the team, and
     * tell the task's followers and anyone it @-mentions.
     */
    public function handle(User $user, Task $task, string $body, bool $internal = false): Comment
    {
        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
            'is_internal' => $internal && ! $user->isClientOf($task->project->team),
        ]);

        $this->notifications->commentPosted($comment, $this->mentions->handle($comment));

        return $comment;
    }
}
