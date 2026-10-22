<?php

namespace App\Notifications\Tasks;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CommentPosted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Comment $comment)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
            ->line('> '.str_replace("\n", "\n> ", e(Str::limit($this->comment->body, 1000))))
            ->when($this->comment->is_internal, fn (MailMessage $message) => $message
                ->line(__('This is an internal note. Clients can’t see it.')))
            ->action(__('Reply'), $task->url());
    }
}
