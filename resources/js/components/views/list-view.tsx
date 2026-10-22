import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { AddTaskRow } from '@/components/tasks/add-task-row';
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
    const groups = useGroups(tasks, settings.group_by ?? 'status', settings);
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

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
        <div className="flex flex-col gap-6 p-4">
            {groups.map((group) => (
                <section key={group.key}>
                    <button
                        type="button"
                        className="mb-1 flex items-center gap-2 rounded px-1 py-1 text-sm"
                        onClick={() => toggle(group.key)}
                    >
                        <ChevronRight
                            className={cn(
                                'size-4 text-muted-foreground transition-transform',
                                !collapsed.has(group.key) && 'rotate-90',
                            )}
                        />
                        {group.label}
                        <span className="text-muted-foreground">
                            {group.tasks.length}
                        </span>
                    </button>

                    {!collapsed.has(group.key) && (
                        <div className="overflow-hidden rounded-lg border">
                            <div
                                className={cn(
                                    ROW_GRID,
                                    'border-b bg-muted/40 px-3 py-1.5 text-xs font-medium text-muted-foreground',
                                )}
                            >
                                <span>Name</span>
                                <span>Assignee</span>
                                <span>Due date</span>
                                <span className="hidden sm:block">
                                    Priority
                                </span>
                            </div>
                            {group.tasks.map((task) => (
                                <ListRow
                                    key={task.id}
                                    task={task}
                                    showStatus={settings.group_by !== 'status'}
                                />
                            ))}
                            <AddTaskRow defaults={group.defaults} />
                        </div>
                    )}
                </section>
            ))}
        </div>
    );
}

function ListRow({ task, showStatus }: { task: Task; showStatus: boolean }) {
    const { openTask } = useProject();

    return (
        <div
            role="button"
            tabIndex={0}
            className={cn(
                ROW_GRID,
                'cursor-pointer border-b px-3 py-1.5 text-sm hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none',
            )}
            onClick={() => openTask(task)}
            onKeyDown={(event) => event.key === 'Enter' && openTask(task)}
        >
            <div className="flex min-w-0 items-center gap-2">
                <StatusToggle task={task} />
                <span
                    className={cn(
                        'truncate',
                        task.completed_at &&
                            'text-muted-foreground line-through',
                    )}
                >
                    {task.title}
                </span>
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

function useGroups(
    tasks: Task[],
    groupBy: GroupBy,
    settings: ViewSettings,
): Group[] {
    const { statuses, members, priorities } = useProject();

    if (groupBy === 'status') {
        return statuses
            .filter((status) => settings.show_closed || !status.is_done)
            .map((status) => ({
                key: `status-${status.id}`,
                label: <StatusBadge status={status} />,
                tasks: tasks.filter((task) => task.status_id === status.id),
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
