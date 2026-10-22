import {
    closestCorners,
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    DragEndEvent,
    DragOverEvent,
    DragStartEvent,
    UniqueIdentifier,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { AddTaskRow } from '@/components/tasks/add-task-row';
import {
    MemberAvatar,
    PriorityFlag,
    StatusBadge,
} from '@/components/tasks/pickers';
import { TaskBadges } from '@/components/tasks/task-badges';
import { formatDue, isOverdue, moveTask } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Status, Task, ViewSettings } from '@/types';

/** Task ids per status id, top to bottom. */
type Columns = Record<number, number[]>;

const COLUMN_PREFIX = 'column-';

export function BoardView({
    tasks,
    settings,
}: {
    tasks: Task[];
    settings: ViewSettings;
}) {
    const { statuses, canEdit } = useProject();
    const tasksById = new Map(tasks.map((task) => [task.id, task]));
    const visibleStatuses = statuses.filter(
        (status) => settings.show_closed || !status.is_done,
    );

    // Tasks arrive sorted by position, so each column keeps that order.
    const fromProps: Columns = Object.fromEntries(
        statuses.map((status) => [
            status.id,
            tasks
                .filter((task) => task.status_id === status.id)
                .map((task) => task.id),
        ]),
    );

    // While dragging, and until the server confirms a drop, show the local
    // arrangement so cards don't jump back to where they started.
    const [pending, setPending] = useState<Columns | null>(null);
    const [activeId, setActiveId] = useState<number | null>(null);
    const columns = pending ?? fromProps;

    const sensors = useSensors(
        // A small distance keeps plain clicks (to open a task) from starting a drag.
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const columnOf = (id: UniqueIdentifier): number | undefined => {
        if (typeof id === 'string' && id.startsWith(COLUMN_PREFIX)) {
            return Number(id.slice(COLUMN_PREFIX.length));
        }

        const statusId = Object.keys(columns).find((key) =>
            columns[Number(key)].includes(Number(id)),
        );

        return statusId === undefined ? undefined : Number(statusId);
    };

    const onDragStart = ({ active }: DragStartEvent) => {
        setActiveId(Number(active.id));
        setPending(fromProps);
    };

    // Moving between columns happens while hovering, so the card makes
    // room in the column it's over; reordering within a column is on drop.
    const onDragOver = ({ active, over }: DragOverEvent) => {
        const from = columnOf(active.id);
        const to = over ? columnOf(over.id) : undefined;

        if (!over || from === undefined || to === undefined || from === to) {
            return;
        }

        setPending((current) => {
            const next = { ...(current ?? fromProps) };
            const target = next[to].filter((id) => id !== Number(active.id));
            const overIndex = target.indexOf(Number(over.id));

            next[from] = next[from].filter((id) => id !== Number(active.id));
            target.splice(
                overIndex === -1 ? target.length : overIndex,
                0,
                Number(active.id),
            );
            next[to] = target;

            return next;
        });
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        setActiveId(null);

        const task = tasksById.get(Number(active.id));
        const statusId = columnOf(active.id);

        if (!task || !over || statusId === undefined || !pending) {
            setPending(null);

            return;
        }

        let order = pending[statusId];
        const from = order.indexOf(task.id);
        const to = order.indexOf(Number(over.id));

        if (to !== -1 && from !== to) {
            order = arrayMove(order, from, to);
        }

        const unchanged =
            statusId === task.status_id &&
            order.join() === fromProps[statusId].join();

        if (unchanged) {
            setPending(null);

            return;
        }

        setPending({ ...pending, [statusId]: order });
        moveTask(task, statusId, order, () => setPending(null));
    };

    const activeTask = activeId ? tasksById.get(activeId) : undefined;

    return (
        <DndContext
            id="project-board"
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragEnd={onDragEnd}
            onDragCancel={() => {
                setActiveId(null);
                setPending(null);
            }}
        >
            <div className="flex items-start gap-3 overflow-x-auto p-4">
                {visibleStatuses.map((status) => (
                    <BoardColumn
                        key={status.id}
                        status={status}
                        tasks={(columns[status.id] ?? [])
                            .map((id) => tasksById.get(id))
                            .filter((task): task is Task => !!task)}
                        canEdit={canEdit}
                    />
                ))}
            </div>

            <DragOverlay>
                {activeTask && (
                    <TaskCard
                        task={activeTask}
                        className="rotate-2 shadow-lg ring-1 ring-primary/30"
                    />
                )}
            </DragOverlay>
        </DndContext>
    );
}

function BoardColumn({
    status,
    tasks,
    canEdit,
}: {
    status: Status;
    tasks: Task[];
    canEdit: boolean;
}) {
    const { setNodeRef, isOver } = useDroppable({
        id: `${COLUMN_PREFIX}${status.id}`,
    });

    return (
        <section
            className={cn(
                'flex w-72 shrink-0 flex-col rounded-lg bg-muted/50 transition-colors',
                isOver && 'bg-muted',
            )}
        >
            <header className="flex items-center gap-2 px-3 pt-3 pb-2">
                <StatusBadge status={status} />
                <span className="text-sm text-muted-foreground">
                    {tasks.length}
                </span>
            </header>

            <SortableContext
                id={`${COLUMN_PREFIX}${status.id}`}
                items={tasks.map((task) => task.id)}
                strategy={verticalListSortingStrategy}
            >
                <div
                    ref={setNodeRef}
                    className="flex min-h-16 flex-col gap-2 px-2 pb-2"
                >
                    {tasks.map((task) => (
                        <SortableCard
                            key={task.id}
                            task={task}
                            disabled={!canEdit}
                        />
                    ))}
                </div>
            </SortableContext>

            <AddTaskRow
                defaults={{ status_id: status.id }}
                className="rounded-b-lg"
            />
        </section>
    );
}

function SortableCard({ task, disabled }: { task: Task; disabled: boolean }) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: task.id, disabled });

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Translate.toString(transform),
                transition,
            }}
            className={cn(isDragging && 'opacity-40')}
            {...attributes}
            {...listeners}
        >
            <TaskCard task={task} />
        </div>
    );
}

function TaskCard({ task, className }: { task: Task; className?: string }) {
    const { members, openTask, canEdit } = useProject();
    const assignee = members.find((member) => member.id === task.assignee_id);
    const overdue = isOverdue(task);

    return (
        <article
            role="button"
            tabIndex={-1}
            className={cn(
                'flex flex-col gap-2 rounded-md border bg-background p-3 text-sm shadow-xs hover:border-foreground/20',
                canEdit && 'cursor-grab active:cursor-grabbing',
                className,
            )}
            onClick={() => openTask(task)}
        >
            <p
                className={cn(
                    'line-clamp-3 font-medium',
                    task.completed_at && 'text-muted-foreground line-through',
                )}
            >
                {task.title}
            </p>

            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                {task.priority && <PriorityFlag priority={task.priority} />}
                {task.due_date && (
                    <span
                        className={cn(
                            'flex items-center gap-1 text-xs text-muted-foreground',
                            overdue && 'text-red-500',
                        )}
                    >
                        <CalendarDays className="size-3.5" />
                        {formatDue(task.due_date)}
                    </span>
                )}
                <TaskBadges task={task} />
                {assignee && (
                    <MemberAvatar
                        member={assignee}
                        className="ml-auto size-6"
                    />
                )}
            </div>
        </article>
    );
}
