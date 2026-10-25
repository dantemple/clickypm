import { ArrowDownToLine, ArrowUpFromLine, X } from 'lucide-react';
import { useProject } from '@/components/projects/project-context';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { addDependency, removeDependency } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Task, TaskDependency } from '@/types';

/**
 * What a task waits for and what waits for it. Part of the Gantt module;
 * the panel only shows it once there are links or someone adds one.
 */
export function TaskDependencies({
    task,
    tasks,
    dependencies,
    autoFocus = false,
}: {
    task: Task;
    tasks: Task[];
    dependencies: TaskDependency[];
    autoFocus?: boolean;
}) {
    const { project, canEdit, openTask } = useProject();
    const byId = new Map(tasks.map((t) => [t.id, t]));
    const blockedBy = dependencies.filter((d) => d.successor_id === task.id);
    const blocks = dependencies.filter((d) => d.predecessor_id === task.id);
    const linked = new Set([
        task.id,
        ...blockedBy.map((d) => d.predecessor_id),
        ...blocks.map((d) => d.successor_id),
    ]);
    const candidates = tasks.filter(
        (t) => !linked.has(t.id) && !t.needs_triage,
    );

    const row = (dependency: TaskDependency, other: Task | undefined) => (
        <li
            key={dependency.id}
            className="group flex items-center gap-2 rounded px-1 py-1 hover:bg-muted/50"
        >
            <span
                className={cn(
                    'min-w-0 flex-1 truncate text-left text-sm',
                    other?.completed_at && 'text-muted-foreground line-through',
                )}
            >
                {other ? (
                    <button
                        type="button"
                        className="truncate hover:underline"
                        onClick={() => openTask(other)}
                    >
                        {other.title}
                    </button>
                ) : (
                    <span className="text-muted-foreground">
                        A task you can’t see
                    </span>
                )}
            </span>
            {canEdit && (
                <button
                    type="button"
                    title="Remove link"
                    className="rounded p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive focus-visible:opacity-100"
                    onClick={() => removeDependency(project.id, dependency)}
                >
                    <X className="size-3.5" />
                </button>
            )}
        </li>
    );

    return (
        <section className="flex flex-col gap-2 border-t px-4 py-3">
            <h3 className="text-sm font-medium">Dependencies</h3>

            {blockedBy.length > 0 && (
                <div>
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <ArrowDownToLine className="size-3.5" /> Waits for
                    </p>
                    <ul className="flex flex-col">
                        {blockedBy.map((d) =>
                            row(d, byId.get(d.predecessor_id)),
                        )}
                    </ul>
                </div>
            )}

            {blocks.length > 0 && (
                <div>
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <ArrowUpFromLine className="size-3.5" /> Holds up
                    </p>
                    <ul className="flex flex-col">
                        {blocks.map((d) => row(d, byId.get(d.successor_id)))}
                    </ul>
                </div>
            )}

            {canEdit && candidates.length > 0 && (
                <Select
                    key={dependencies.length}
                    onValueChange={(value) =>
                        addDependency(task, Number(value))
                    }
                >
                    <SelectTrigger
                        className="h-8 w-full text-muted-foreground"
                        aria-label="Add a task this one waits for"
                        autoFocus={autoFocus}
                    >
                        <SelectValue placeholder="Waits for… (choose a task)" />
                    </SelectTrigger>
                    <SelectContent>
                        {candidates.map((candidate) => (
                            <SelectItem
                                key={candidate.id}
                                value={String(candidate.id)}
                            >
                                {candidate.title}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
        </section>
    );
}
