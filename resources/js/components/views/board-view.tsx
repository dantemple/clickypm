import {
    closestCorners,
    DndContext,
    DragOverlay,
    useDroppable,
} from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { CalendarDays } from 'lucide-react';
import { useProject } from '@/components/projects/project-context';
import { AddTaskRow } from '@/components/tasks/add-task-row';
import {
    MemberAvatar,
    PriorityFlag,
    StatusBadge,
} from '@/components/tasks/pickers';
import { TagBadge } from '@/components/tasks/tag-badge';
import { TaskBadges } from '@/components/tasks/task-badges';
import { statusDropId, useTaskDrag } from '@/hooks/use-task-drag';
import { formatDue, isOverdue } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Status, Task, ViewSettings } from '@/types';

export function BoardView({
    tasks,
    settings,
}: {
    tasks: Task[];
    settings: ViewSettings;
}) {
    const { statuses, canEdit } = useProject();
    const { columns, tasksById, activeTask, sensors, handlers } =
        useTaskDrag(tasks);
    const visibleStatuses = statuses.filter(
        (status) => settings.show_closed || !status.is_done,
    );

    return (
        <DndContext
            id="project-board"
            sensors={sensors}
            collisionDetection={closestCorners}
            {...handlers}
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
        id: statusDropId(status.id),
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
                id={statusDropId(status.id)}
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

            {task.tags.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {task.tags.map((tag) => (
                        <TagBadge key={tag.id} tag={tag} />
                    ))}
                </div>
            )}

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
