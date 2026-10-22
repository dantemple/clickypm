import { Check, ChevronRight, Inbox, MessageSquare, X } from 'lucide-react';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { Button } from '@/components/ui/button';
import { acceptTask, deleteTask, formatDateTime } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Task } from '@/types';

/**
 * Client requests waiting to be triaged. The team accepts them onto the
 * project or declines (deletes) them; clients just see what's pending.
 */
export function RequestsInbox({ requests }: { requests: Task[] }) {
    const { canEdit, openTask } = useProject();
    const [open, setOpen] = useState(true);

    if (requests.length === 0) {
        return null;
    }

    const decline = (task: Task) => {
        if (confirm(`Decline and delete the request “${task.title}”?`)) {
            deleteTask(task);
        }
    };

    return (
        <section className="mx-4 mt-4 overflow-hidden rounded-lg border border-amber-300/70 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/5">
            <button
                type="button"
                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium"
                onClick={() => setOpen(!open)}
            >
                <ChevronRight
                    className={cn(
                        'size-4 text-muted-foreground transition-transform',
                        open && 'rotate-90',
                    )}
                />
                <Inbox className="size-4 text-amber-600" />
                {canEdit
                    ? 'Client requests to triage'
                    : 'Requests awaiting review'}
                <span className="text-muted-foreground">{requests.length}</span>
            </button>

            {open && (
                <ul className="divide-y divide-amber-200/70 border-t border-amber-200/70 dark:divide-amber-500/20 dark:border-amber-500/20">
                    {requests.map((task) => (
                        <li
                            key={task.id}
                            className="flex items-center gap-3 px-3 py-2 text-sm"
                        >
                            <button
                                type="button"
                                className="min-w-0 flex-1 text-left"
                                onClick={() => openTask(task)}
                            >
                                <span className="block truncate font-medium hover:underline">
                                    {task.title}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {task.created_by?.name ?? 'Someone'} ·{' '}
                                    {formatDateTime(task.created_at)}
                                </span>
                            </button>
                            {task.comments_count > 0 && (
                                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                    <MessageSquare className="size-3.5" />
                                    {task.comments_count}
                                </span>
                            )}
                            {canEdit && (
                                <div className="flex shrink-0 gap-1">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-7"
                                        onClick={() => acceptTask(task)}
                                    >
                                        <Check /> Accept
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 text-muted-foreground"
                                        onClick={() => decline(task)}
                                    >
                                        <X /> Decline
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
