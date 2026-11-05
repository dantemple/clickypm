<?php

namespace App\Notifications\Tasks;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class CommentPosted extends TaskNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Comment $comment)
    {
        //
    }

    public function task(): Task
    {
        return $this->comment->task;
    }

    public function actor(): ?User
    {
        return $this->comment->user;
    }

    public function message(): string
    {
        $name = $this->comment->user->name ?? __('Someone');

        return $this->comment->is_internal
            ? __(':name added an internal note', ['name' => $name])
            : __(':name commented', ['name' => $name]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->comment->task;
        $author = $this->comment->user->name ?? __('Someone');

        return (new MailMessage)
            ->subject(__('[:project] :name commented on ":title"', [
                'project' => $task->project->name,
                'name' => $author,
                'title' => $task->title,
            ]))
            ->greeting($this->comment->is_internal ? __('New internal note') : __('New comment'))
            ->line(__(':name wrote on **:title**:', ['name' => e($author), 'title' => e($task->title)]))
            ->line('> '.str_replace("\n", "\n> ", e(Str::limit($this->comment->plainBody(), 1000))))
            ->when($this->comment->is_internal, fn (MailMessage $message) => $message
                ->line(__('This is an internal note. Clients can’t see it.')))
            ->action(__('Reply'), $task->url());
    }
}
