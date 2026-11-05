<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class TaskAssigned extends TaskNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Task $task, public ?User $assigner)
    {
        //
    }

    public function task(): Task
    {
        return $this->task;
    }

    public function actor(): ?User
    {
        return $this->assigner;
    }

    public function message(): string
    {
        return __(':name assigned you a task', ['name' => $this->assigner->name ?? __('Someone')]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->task->project;

        $message = (new MailMessage)
            ->subject(__('[:project] Assigned to you: :title', ['project' => $project->name, 'title' => $this->task->title]))
            ->greeting(__('New assignment'))
            ->line(__(':name assigned you a task in :project:', [
                'name' => e($this->assigner->name ?? __('Someone')),
                'project' => e($project->name),
            ]))
            ->line('**'.e($this->task->title).'**');

        if ($this->task->due_date) {
            $message->line(__('Due :date', ['date' => $this->task->due_date->toFormattedDateString()]));
        }

        if ($this->task->priority) {
            $message->line(__('Priority: :priority', ['priority' => $this->task->priority->label()]));
        }

        if ($this->task->description) {
            $message->line(e(Str::limit($this->task->description, 500)));
        }

        return $message->action(__('Open task'), $this->task->url());
    }
}
