<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ClientRequestCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Task $task)
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
        $project = $this->task->project;

        $message = (new MailMessage)
            ->subject(__('[:project] New request: :title', ['project' => $project->name, 'title' => $this->task->title]))
            ->greeting(__('New client request'))
            ->line(__(':name asked for something in :project:', [
                'name' => $this->task->creator->name ?? __('A client'),
                'project' => $project->name,
            ]))
            ->line('**'.e($this->task->title).'**');

        if ($this->task->description) {
            $message->line(e(Str::limit($this->task->description, 500)));
        }

        return $message
            ->action(__('Review request'), $this->task->url())
            ->line(__('It stays in the project’s request inbox until someone accepts or declines it.'));
    }
}
