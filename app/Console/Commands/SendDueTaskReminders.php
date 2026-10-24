<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\DueTasksReminder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SendDueTaskReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:send-due-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email everyone a list of their open tasks that are due today or overdue';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tasks = Task::query()
            ->whereNotNull('assignee_id')
            ->whereNull('completed_at')
            ->where('needs_triage', false)
            ->whereDate('due_date', '<=', today())
            // Leave out tasks whose project is in the trash or whose workspace is gone.
            ->whereHas('project.team')
            ->with(['project.team', 'assignee'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $sent = 0;

        /** @var Collection<int, Task> $assigned */
        foreach ($tasks->groupBy('assignee_id') as $assigned) {
            /** @var User $user */
            $user = $assigned->first()->assignee;

            if (! $user->reminder_emails) {
                continue;
            }

            // People removed from a workspace can still be named on its tasks.
            $visible = $assigned->filter(fn (Task $task) => $user->can('view', $task->project))->values();

            if ($visible->isEmpty()) {
                continue;
            }

            $user->notify(new DueTasksReminder($visible));
            $sent++;
        }

        $this->info(trans_choice(':count reminder sent.|:count reminders sent.', $sent));

        return self::SUCCESS;
    }
}
