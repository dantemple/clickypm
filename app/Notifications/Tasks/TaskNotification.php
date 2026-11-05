<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Something happened on a task that one of its followers should hear about.
 *
 * Every one of these lands in the person's in-app inbox (and is pushed to
 * their open tabs so the bell updates). Whether they are also emailed is up
 * to the "task emails" switch in their notification settings.
 */
abstract class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The task the notification is about.
     */
    abstract public function task(): Task;

    /**
     * The person whose activity caused the notification, if anyone.
     */
    abstract public function actor(): ?User;

    /**
     * A one-line summary for the inbox, such as "Dan assigned you a task".
     */
    abstract public function message(): string;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($notifiable instanceof User && $notifiable->task_emails) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * What the inbox stores and broadcasts: enough to show the entry and open
     * the task, without having to load anything else.
     *
     * @return array{message: string, task: array{id: int, title: string}, project: array{id: int, name: string, color: string}, actor: string|null, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $task = $this->task();
        $project = $task->project;

        return [
            'message' => $this->message(),
            'task' => ['id' => $task->id, 'title' => $task->title],
            'project' => ['id' => $project->id, 'name' => $project->name, 'color' => $project->color],
            'actor' => $this->actor()?->name,
            'url' => $task->url(),
        ];
    }
}
