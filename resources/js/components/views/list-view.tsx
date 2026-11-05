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
import { ChevronRight, GripVertical } from 'lucide-react';
import type { CSSProperties, ReactNode, Ref } from 'react';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { AddTaskRow } from '@/components/tasks/add-task-row';
import { TagList } from '@/components/tasks/tag-badge';
import { TaskBadges } from '@/components/tasks/task-badges';
import {
    AssigneePicker,
    DueDatePicker,
    MemberAvatar,
    PriorityFlag,
    PriorityPicker,
    StatusBadge,
    StatusPicker,
    StatusToggle,
} from '@/components/tasks/pickers';
import { statusDropId, useTaskDrag } from '@/hooks/use-task-drag';
import { taskKey } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { GroupBy, Task, TaskInput, ViewSettings } from '@/types';

type Group = {
    key: string;
    label: ReactNode;
    tasks: Task[];
    defaults: TaskInput;
};

const ROW_GRID =
    'grid grid-cols-[minmax(0,1fr)_3rem_6.5rem] items-center gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_7.5rem_7rem]';

export function ListView({
    tasks,
    settings,
}: {
    tasks: Task[];
    settings: ViewSettings;
}) {
    const { canEdit } = useProject();
    const groupBy = settings.group_by ?? 'status';
    const drag = useTaskDrag(tasks);
    const groups = useGroups(tasks, groupBy, settings, drag);
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

    // Dragging a row between groups changes its status, so it only makes
    // sense when the groups are statuses.
    const draggable = canEdit && groupBy === 'status';

    const toggle = (key: string) =>
        setCollapsed((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });

    return (
        <DndContext
            id="project-list"
            sensors={drag.sensors}
            collisionDetection={closestCorners}
            {...drag.handlers}
        >
            <div className="flex flex-col gap-6 p-4">
                {groups.map((group) => (
                    <ListGroup
                        key={group.key}
                        group={group}
                        collapsed={collapsed.has(group.key)}
                        onToggle={() => toggle(group.key)}
                        draggable={draggable}
                        showStatus={groupBy !== 'status'}
                    />
                ))}
            </div>

            <DragOverlay>
                {drag.activeTask && (
                    <ListRow
                        task={drag.activeTask}
                        showStatus={false}
                        draggable
                        className="rounded-md border bg-background shadow-lg ring-1 ring-primary/30"
                    />
                )}
            </DragOverlay>
        </DndContext>
    );
}

function ListGroup({
    group,
    collapsed,
    onToggle,
    draggable,
    showStatus,
}: {
    group: Group;
    collapsed: boolean;
    onToggle: () => void;
    draggable: boolean;
    showStatus: boolean;
}) {
    // The whole section takes drops, so a task can land on a collapsed or
    // empty group by its heading.
    const { setNodeRef, isOver } = useDroppable({
        id: group.key,
        disabled: !draggable,
    });

    return (
        <section ref={setNodeRef}>
            <button
                type="button"
                className={cn(
                    'mb-1 flex items-center gap-2 rounded px-1 py-1 text-sm',
                    isOver && collapsed && 'bg-muted',
                )}
                onClick={onToggle}
            >
                <ChevronRight
                    className={cn(
                        'size-4 text-muted-foreground transition-transform',
                        !collapsed && 'rotate-90',
                    )}
                />
                {group.label}
                <span className="text-muted-foreground">
                    {group.tasks.length}
                </span>
            </button>

            {!collapsed && (
                <div
                    className={cn(
                        'overflow-hidden rounded-lg border transition-colors',
                        isOver && 'border-primary/50',
                    )}
                >
                    <div
                        className={cn(
                            ROW_GRID,
                            rowPadding(draggable),
                            'border-b bg-muted/40 py-1.5 text-xs font-medium text-muted-foreground',
                        )}
                    >
                        <span>Name</span>
                        <span>Assignee</span>
                        <span>Due date</span>
                        <span className="hidden sm:block">Priority</span>
                    </div>
                    <SortableContext
                        id={group.key}
                        items={group.tasks.map((task) => task.id)}
                        strategy={verticalListSortingStrategy}
                    >
                        {group.tasks.map((task) => (
                            <SortableRow
                                key={task.id}
                                task={task}
                                showStatus={showStatus}
                                draggable={draggable}
                            />
                        ))}
                    </SortableContext>
                    <AddTaskRow defaults={group.defaults} />
                </div>
            )}
        </section>
    );
}

function SortableRow({
    task,
    showStatus,
    draggable,
}: {
    task: Task;
    showStatus: boolean;
    draggable: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        setActivatorNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: task.id, disabled: !draggable });

    return (
        <ListRow
            ref={setNodeRef}
            task={task}
            showStatus={showStatus}
            draggable={draggable}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            className={cn(isDragging && 'opacity-40')}
            handle={
                draggable && (
                    <button
                        type="button"
                        ref={setActivatorNodeRef}
                        aria-label={`Move ${task.title}`}
                        className="absolute top-1/2 left-1 -translate-y-1/2 cursor-grab rounded text-muted-foreground opacity-0 group-hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing pointer-coarse:opacity-100"
                        onClick={(event) => event.stopPropagation()}
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="size-4" />
                    </button>
                )
            }
        />
    );
}

function ListRow({
    ref,
    task,
    showStatus,
    draggable,
    handle,
    style,
    className,
}: {
    ref?: Ref<HTMLDivElement>;
    task: Task;
    showStatus: boolean;
    draggable: boolean;
    handle?: ReactNode;
    style?: CSSProperties;
    className?: string;
}) {
    const { project, openTask } = useProject();

    return (
        <div
            ref={ref}
            role="button"
            tabIndex={0}
            style={style}
            className={cn(
                ROW_GRID,
                rowPadding(draggable),
                'group relative cursor-pointer border-b py-1.5 text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none',
                className,
            )}
            onClick={() => openTask(task)}
            onKeyDown={(event) =>
                event.key === 'Enter' &&
                event.target === event.currentTarget &&
                openTask(task)
            }
        >
            {handle}
            <div className="flex min-w-0 items-center gap-2">
                <StatusToggle task={task} />
                <span className="hidden shrink-0 font-mono text-xs text-muted-foreground sm:inline">
                    {taskKey(project.key, task)}
                </span>
                <span
                    className={cn(
                        'truncate',
                        task.completed_at &&
                            'text-muted-foreground line-through',
                    )}
                >
                    {task.title}
                </span>
                <TagList tags={task.tags} className="hidden md:flex" />
                <TaskBadges task={task} />
                {showStatus && (
                    <StatusPicker task={task} className="ml-auto shrink-0" />
                )}
            </div>
            <div>
                <AssigneePicker task={task} />
            </div>
            <div>
                <DueDatePicker task={task} />
            </div>
            <div className="hidden sm:block">
                <PriorityPicker task={task} />
            </div>
        </div>
    );
}

/** Draggable rows keep a gutter on the left for the drag handle. */
function rowPadding(draggable: boolean): string {
    return draggable ? 'pr-3 pl-7' : 'px-3';
}

function useGroups(
    tasks: Task[],
    groupBy: GroupBy,
    settings: ViewSettings,
    drag: ReturnType<typeof useTaskDrag>,
): Group[] {
    const { statuses, members, priorities } = useProject();

    if (groupBy === 'status') {
        return statuses
            .filter((status) => settings.show_closed || !status.is_done)
            .map((status) => ({
                key: statusDropId(status.id),
                label: <StatusBadge status={status} />,
                // Follow the drag in progress rather than the tasks' own
                // statuses, so rows make room and stay put after a drop.
                tasks: (drag.columns[status.id] ?? [])
                    .map((id) => drag.tasksById.get(id))
                    .filter((task): task is Task => !!task),
                defaults: { status_id: status.id },
            }));
    }

    if (groupBy === 'priority') {
        return [
            ...priorities.map((priority) => ({
                key: `priority-${priority.value}`,
                label: (
                    <span className="flex items-center gap-1.5 font-medium">
                        <PriorityFlag priority={priority.value} />
                        {priority.label}
                    </span>
                ),
                tasks: tasks.filter((task) => task.priority === priority.value),
                defaults: { priority: priority.value },
            })),
            {
                key: 'priority-none',
                label: (
                    <span className="flex items-center gap-1.5 font-medium text-muted-foreground">
                        <PriorityFlag priority={null} /> No priority
                    </span>
                ),
                tasks: tasks.filter((task) => task.priority === null),
                defaults: {},
            },
        ].filter(
            (group) => group.tasks.length > 0 || group.key === 'priority-none',
        );
    }

    return [
        ...members.map((member) => ({
            key: `assignee-${member.id}`,
            label: (
                <span className="flex items-center gap-2 font-medium">
                    <MemberAvatar member={member} className="size-5" />
                    {member.name}
                </span>
            ),
            tasks: tasks.filter((task) => task.assignee_id === member.id),
            defaults: { assignee_id: member.id },
        })),
        {
            key: 'assignee-none',
            label: (
                <span className="font-medium text-muted-foreground">
                    Unassigned
                </span>
            ),
            tasks: tasks.filter((task) => task.assignee_id === null),
            defaults: {},
        },
    ].filter(
        (group) => group.tasks.length > 0 || group.key === 'assignee-none',
    );
}
