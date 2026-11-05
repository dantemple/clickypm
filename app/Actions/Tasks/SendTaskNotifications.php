<?php

namespace App\Actions\Tasks;

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Tasks\ClientRequestCreated;
use App\Notifications\Tasks\CommentMentioned;
use App\Notifications\Tasks\CommentPosted;
use App\Notifications\Tasks\TaskAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about activity on a task.
 *
 * A task's followers are its creator, its assignee, everyone who has
 * commented on it and everyone who has been @-mentioned in a comment.
 * Nobody is told about their own activity, clients never
 * hear about internal notes, nobody is emailed about a project they can no
 * longer see, and people who turned task emails off in their settings hear
 * nothing at all.
 */
class SendTaskNotifications
{
    /**
     * Tell someone a task has just been assigned to them.
     */
    public function taskAssigned(Task $task, ?User $actor): void
    {
        if (! $task->assignee) {
            return;
        }

        $this->send(collect([$task->assignee]), $task, $actor, new TaskAssigned($task, $actor));
    }

    /**
     * Tell the team a client has asked for something.
     */
    public function clientRequestCreated(Task $task): void
    {
        $team = $task->project->team;

        $this->send(
            $this->members($team, [TeamRole::Owner, TeamRole::Admin, TeamRole::Member]),
            $task,
            $task->creator,
            new ClientRequestCreated($task),
        );
    }

    /**
     * Tell the task's followers about a new comment or internal note. Anyone
     * the comment @-mentions gets a "you were mentioned" email instead.
     *
     * @param  Collection<int, User>  $mentioned
     */
    public function commentPosted(Comment $comment, Collection $mentioned): void
    {
        $task = $comment->task;
        $team = $task->project->team;
        $author = $comment->user;

        $recipients = $this->followers($task)
            ->reject(fn (User $user) => $mentioned->contains(fn (User $mention) => $mention->is($user)));

        // Make sure a client's message always reaches someone who can act on it.
        if ($author && $author->isClientOf($team)) {
            $recipients = $recipients->merge($this->members($team, [TeamRole::Owner, TeamRole::Admin]));
        }

        if ($comment->is_internal) {
            $recipients = $recipients->reject(fn (User $user) => $user->isClientOf($team));
        }

        $this->commentMentioned($comment, $mentioned);
        $this->send($recipients, $task, $author, new CommentPosted($comment));
    }

    /**
     * Tell people they've been @-mentioned in a comment.
     *
     * @param  Collection<int, User>  $mentioned
     */
    public function commentMentioned(Comment $comment, Collection $mentioned): void
    {
        if ($mentioned->isEmpty()) {
            return;
        }

        $this->send($mentioned, $comment->task, $comment->user, new CommentMentioned($comment));
    }

    /**
     * @return Collection<int, User>
     */
    private function followers(Task $task): Collection
    {
        $ids = collect([$task->created_by, $task->assignee_id])
            ->merge($task->comments()->pluck('user_id'))
            ->merge(User::whereHas('mentionedIn', fn ($query) => $query->where('task_id', $task->id))->pluck('id'))
            ->filter()
            ->unique();

        return User::whereKey($ids)->get();
    }

    /**
     * @param  array<int, TeamRole>  $roles
     * @return Collection<int, User>
     */
    private function members(Team $team, array $roles): Collection
    {
        return $team->members()
            ->wherePivotIn('role', array_map(fn (TeamRole $role) => $role->value, $roles))
            ->get();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Collection $recipients, Task $task, ?User $actor, object $notification): void
    {
        $recipients = $recipients
            ->unique('id')
            ->reject(fn (User $user) => $user->is($actor))
            ->filter(fn (User $user) => $user->task_emails)
            ->filter(fn (User $user) => $user->can('view', $task->project))
            ->values();

        Notification::send($recipients, $notification);
    }
}
