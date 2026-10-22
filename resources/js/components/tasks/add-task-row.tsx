import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { createTask } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { TaskInput } from '@/types';

/**
 * A "+ Add task" line that turns into an input. Extra attributes (status,
 * priority, assignee) let a grouped list drop the task into that group.
 */
export function AddTaskRow({
    defaults = {},
    className,
}: {
    defaults?: TaskInput;
    className?: string;
}) {
    const { project, canEdit } = useProject();
    const [adding, setAdding] = useState(false);
    const [title, setTitle] = useState('');

    // Clients raise requests from the toolbar instead.
    if (!canEdit) {
        return null;
    }

    const submit = () => {
        const trimmed = title.trim();

        if (trimmed === '') {
            setAdding(false);

            return;
        }

        createTask(
            project.id,
            { ...defaults, title: trimmed },
            { onSuccess: () => setTitle('') },
        );
    };

    if (!adding) {
        return (
            <button
                type="button"
                className={cn(
                    'flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                    className,
                )}
                onClick={() => setAdding(true)}
            >
                <Plus className="size-4" /> Add task
            </button>
        );
    }

    return (
        <div className={cn('flex items-center gap-2 px-3 py-1.5', className)}>
            <Plus className="size-4 shrink-0 text-muted-foreground" />
            <input
                autoFocus
                value={title}
                placeholder="Task name — press Enter to save, Esc to cancel"
                className="h-7 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground/70"
                onChange={(event) => setTitle(event.target.value)}
                onBlur={() => title.trim() === '' && setAdding(false)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submit();
                    }

                    if (event.key === 'Escape') {
                        setTitle('');
                        setAdding(false);
                    }
                }}
            />
        </div>
    );
}
