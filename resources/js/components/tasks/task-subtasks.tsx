import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { AssigneeMenu, DueDateButton } from '@/components/tasks/pickers';
import { Checkbox } from '@/components/ui/checkbox';
import {
    createSubtask,
    deleteSubtask,
    todayString,
    updateSubtask,
} from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Subtask, Task } from '@/types';

export function TaskSubtasks({
    task,
    subtasks,
}: {
    task: Task;
    subtasks: Subtask[];
}) {
    const { canEdit } = useProject();
    const done = subtasks.filter((subtask) => subtask.completed_at).length;

    if (!canEdit && subtasks.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-t p-4">
            <div className="flex items-center gap-3">
                <h3 className="text-sm font-medium">
                    Subtasks{' '}
                    {subtasks.length > 0 && (
                        <span className="font-normal text-muted-foreground">
                            {done}/{subtasks.length}
                        </span>
                    )}
                </h3>
                {subtasks.length > 0 && (
                    <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-green-500 transition-[width]"
                            style={{
                                width: `${(done / subtasks.length) * 100}%`,
                            }}
                        />
                    </div>
                )}
            </div>

            <ul className="flex flex-col">
                {subtasks.map((subtask) => (
                    <SubtaskRow
                        key={subtask.id}
                        task={task}
                        subtask={subtask}
                    />
                ))}
            </ul>

            {canEdit && <AddSubtask task={task} />}
        </div>
    );
}

function SubtaskRow({ task, subtask }: { task: Task; subtask: Subtask }) {
    const { canEdit } = useProject();
    const [title, setTitle] = useState(subtask.title);
    const done = !!subtask.completed_at;

    const saveTitle = () => {
        const trimmed = title.trim();

        if (trimmed === '') {
            setTitle(subtask.title);
        } else if (trimmed !== subtask.title) {
            updateSubtask(task, subtask, { title: trimmed });
        }
    };

    return (
        <li className="group flex items-center gap-2 rounded px-1 py-1 hover:bg-muted/50">
            <Checkbox
                checked={done}
                disabled={!canEdit}
                aria-label={done ? 'Mark as not done' : 'Mark as done'}
                onCheckedChange={(checked) =>
                    updateSubtask(task, subtask, {
                        completed: checked === true,
                    })
                }
            />
            {canEdit ? (
                <input
                    value={title}
                    aria-label="Subtask name"
                    className={cn(
                        'h-7 min-w-0 flex-1 rounded bg-transparent px-1 text-sm outline-none focus:bg-background focus:ring-2 focus:ring-ring',
                        done && 'text-muted-foreground line-through',
                    )}
                    onChange={(event) => setTitle(event.target.value)}
                    onBlur={saveTitle}
                    onKeyDown={(event) =>
                        event.key === 'Enter' && event.currentTarget.blur()
                    }
                />
            ) : (
                <span
                    className={cn(
                        'min-w-0 flex-1 px-1 text-sm',
                        done && 'text-muted-foreground line-through',
                    )}
                >
                    {subtask.title}
                </span>
            )}
            <DueDateButton
                date={subtask.due_date}
                overdue={
                    !done &&
                    !!subtask.due_date &&
                    subtask.due_date < todayString()
                }
                onChange={(date) =>
                    updateSubtask(task, subtask, { due_date: date })
                }
            />
            <AssigneeMenu
                assigneeId={subtask.assignee_id}
                size="size-5"
                onChange={(assigneeId) =>
                    updateSubtask(task, subtask, { assignee_id: assigneeId })
                }
            />
            {canEdit && (
                <button
                    type="button"
                    title="Remove subtask"
                    className="rounded p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive focus-visible:opacity-100"
                    onClick={() => deleteSubtask(task, subtask)}
                >
                    <X className="size-3.5" />
                </button>
            )}
        </li>
    );
}

/** Enter adds the subtask and keeps the box open for the next one. */
function AddSubtask({ task }: { task: Task }) {
    const [title, setTitle] = useState('');

    return (
        <form
            className="flex items-center gap-2 px-1"
            onSubmit={(event) => {
                event.preventDefault();

                if (title.trim() !== '') {
                    createSubtask(task, title.trim(), () => setTitle(''));
                }
            }}
        >
            <Plus className="size-4 shrink-0 text-muted-foreground" />
            <input
                value={title}
                aria-label="New subtask"
                placeholder="Add a subtask"
                className="h-7 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                onChange={(event) => setTitle(event.target.value)}
            />
        </form>
    );
}
