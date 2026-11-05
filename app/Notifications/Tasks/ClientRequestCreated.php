<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class ClientRequestCreated extends TaskNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Task $task)
    {
        //
    }

    public function task(): Task
    {
        return $this->task;
    }

    public function actor(): ?User
    {
        return $this->task->creator;
    }

    public function message(): string
    {
        return __(':name sent a new request', ['name' => $this->task->creator->name ?? __('A client')]);
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
