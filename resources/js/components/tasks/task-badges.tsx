import { ListChecks, MessageSquare, Paperclip } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Task } from '@/types';

/** Small subtask-progress and comment-count markers shown next to a task's name. */
export function TaskBadges({ task }: { task: Task }) {
    const allDone =
        task.subtasks_count > 0 &&
        task.subtasks_done_count === task.subtasks_count;

    return (
        <>
            {task.subtasks_count > 0 && (
                <span
                    title="Subtasks done"
                    className={cn(
                        'flex shrink-0 items-center gap-0.5 text-xs text-muted-foreground',
                        allDone && 'text-green-600 dark:text-green-500',
                    )}
                >
                    <ListChecks className="size-3" />
                    {task.subtasks_done_count}/{task.subtasks_count}
                </span>
            )}
            {task.attachments_count > 0 && (
                <span
                    title="Attachments"
                    className="flex shrink-0 items-center gap-0.5 text-xs text-muted-foreground"
                >
                    <Paperclip className="size-3" />
                    {task.attachments_count}
                </span>
            )}
            {task.comments_count > 0 && (
                <span
                    title="Comments"
                    className="flex shrink-0 items-center gap-0.5 text-xs text-muted-foreground"
                >
                    <MessageSquare className="size-3" />
                    {task.comments_count}
                </span>
            )}
        </>
    );
}
