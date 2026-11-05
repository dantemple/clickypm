<?php

namespace App\Notifications\Tasks;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class CommentMentioned extends TaskNotification
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
        return __(':name mentioned you', ['name' => $this->comment->user->name ?? __('Someone')]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->comment->task;
        $author = $this->comment->user->name ?? __('Someone');

        return (new MailMessage)
            ->subject(__('[:project] :name mentioned you on ":title"', [
                'project' => $task->project->name,
                'name' => $author,
                'title' => $task->title,
            ]))
            ->greeting(__('You were mentioned'))
            ->line(__(':name mentioned you on **:title**:', ['name' => e($author), 'title' => e($task->title)]))
            ->line('> '.str_replace("\n", "\n> ", e(Str::limit($this->comment->plainBody(), 1000))))
            ->when($this->comment->is_internal, fn (MailMessage $message) => $message
                ->line(__('This is an internal note. Clients can’t see it.')))
            ->action(__('Reply'), $task->url());
    }
}
