<?php

namespace App\Actions\Tasks;

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Tasks\ClientRequestCreated;
use App\Notifications\Tasks\CommentPosted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about activity on a task.
 *
 * A task's followers are its creator, its assignee and everyone who has
 * commented on it. Nobody is told about their own activity, clients never
 * hear about internal notes, and nobody is emailed about a project they
 * can no longer see.
 */
class SendTaskNotifications
{
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
     * Tell the task's followers about a new comment or internal note.
     */
    public function commentPosted(Comment $comment): void
    {
        $task = $comment->task;
        $team = $task->project->team;
        $author = $comment->user;

        $recipients = $this->followers($task);

        // Make sure a client's message always reaches someone who can act on it.
        if ($author && $author->isClientOf($team)) {
            $recipients = $recipients->merge($this->members($team, [TeamRole::Owner, TeamRole::Admin]));
        }

        if ($comment->is_internal) {
            $recipients = $recipients->reject(fn (User $user) => $user->isClientOf($team));
        }

        $this->send($recipients, $task, $author, new CommentPosted($comment));
    }

    /**
     * @return Collection<int, User>
     */
    private function followers(Task $task): Collection
    {
        $ids = collect([$task->created_by, $task->assignee_id])
            ->merge($task->comments()->pluck('user_id'))
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
            ->filter(fn (User $user) => $user->can('view', $task->project))
            ->values();

        Notification::send($recipients, $notification);
    }
}
