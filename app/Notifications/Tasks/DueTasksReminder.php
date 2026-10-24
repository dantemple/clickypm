<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The morning email listing someone's open tasks that are due today or overdue.
 */
class DueTasksReminder extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     *
     * @param  Collection<int, Task>  $tasks  Tasks due today or earlier, soonest first.
     */
    public function __construct(public Collection $tasks)
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
        $today = today();

        /** @var array{0: Collection<int, Task>, 1: Collection<int, Task>} $groups */
        $groups = $this->tasks->partition(fn (Task $task) => $task->due_date !== null && $task->due_date->lt($today));
        [$overdue, $dueToday] = $groups;

        $message = (new MailMessage)
            ->subject($this->subject($overdue->count(), $dueToday->count()))
            ->greeting(__('Your tasks for :date', ['date' => $today->toFormattedDateString()]));

        if ($overdue->isNotEmpty()) {
            $message->line('**'.__('Overdue (:count)', ['count' => $overdue->count()]).'**');

            $overdue->each(fn (Task $task) => $message->line($this->describe($task, $today)));
        }

        if ($dueToday->isNotEmpty()) {
            $message->line('**'.__('Due today (:count)', ['count' => $dueToday->count()]).'**');

            $dueToday->each(fn (Task $task) => $message->line($this->describe($task, $today)));
        }

        /** @var Task $first */
        $first = $this->tasks->first();

        return $message->action(__('Open dashboard'), route('dashboard', $first->project->team));
    }

    /**
     * A one-line summary of how much is waiting, for the subject.
     */
    private function subject(int $overdue, int $dueToday): string
    {
        $parts = array_filter([
            $overdue ? trans_choice(':count task overdue|:count tasks overdue', $overdue) : null,
            $dueToday ? trans_choice(':count task due today|:count tasks due today', $dueToday) : null,
        ]);

        return __('Your tasks: :summary', ['summary' => implode(', ', $parts)]);
    }

    /**
     * A task as a linked list item with its project and how late it is.
     */
    private function describe(Task $task, CarbonImmutable $today): string
    {
        $days = (int) $task->due_date?->diffInDays($today);

        $when = match (true) {
            $days <= 0 => __('due today'),
            $days === 1 => __('due yesterday'),
            default => __('due :days days ago', ['days' => $days]),
        };

        return sprintf('- [%s](%s) · %s · %s', e($task->title), $task->url(), e($task->project->name), $when);
    }
}
