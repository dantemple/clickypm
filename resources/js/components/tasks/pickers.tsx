import {
    CalendarDays,
    Check,
    CircleCheck,
    Circle,
    Flag,
    UserPlus,
    X,
} from 'lucide-react';
import { useRef } from 'react';
import { useProject } from '@/components/projects/project-context';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { PRIORITY_COLORS, formatDue, isOverdue, updateTask } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Member, Priority, Status, Task } from '@/types';

export function StatusBadge({
    status,
    className,
}: {
    status: Pick<Status, 'name' | 'color'>;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-6 items-center rounded px-2 text-[11px] font-semibold tracking-wide whitespace-nowrap text-white uppercase',
                className,
            )}
            style={{ backgroundColor: status.color }}
        >
            {status.name}
        </span>
    );
}

export function StatusPicker({
    task,
    className,
}: {
    task: Task;
    className?: string;
}) {
    const { statuses, canEdit } = useProject();
    const current = statuses.find((status) => status.id === task.status_id);

    if (!canEdit) {
        return current ? (
            <StatusBadge status={current} className={className} />
        ) : null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'rounded outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    className,
                )}
                onClick={(event) => event.stopPropagation()}
            >
                {current && <StatusBadge status={current} />}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-48"
                onClick={(event) => event.stopPropagation()}
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Status
                </DropdownMenuLabel>
                {statuses.map((status) => (
                    <DropdownMenuItem
                        key={status.id}
                        onSelect={() =>
                            status.id !== task.status_id &&
                            updateTask(task, { status_id: status.id })
                        }
                    >
                        <span
                            className="size-2.5 rounded-full"
                            style={{ backgroundColor: status.color }}
                        />
                        {status.name}
                        {status.id === task.status_id && (
                            <Check className="ml-auto" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** The round "complete" toggle in front of a task's title. */
export function StatusToggle({ task }: { task: Task }) {
    const { statuses, canEdit } = useProject();
    const current = statuses.find((status) => status.id === task.status_id);
    const done = !!current?.is_done;

    const toggle = () => {
        const target = statuses.find((status) => status.is_done !== done);

        if (target) {
            updateTask(task, { status_id: target.id });
        }
    };

    const Icon = done ? CircleCheck : Circle;

    if (!canEdit) {
        return (
            <Icon
                className="size-4 shrink-0"
                style={{ color: current?.color }}
                strokeWidth={done ? 2.5 : 2}
            />
        );
    }

    return (
        <button
            type="button"
            title={done ? 'Mark as not done' : 'Mark as done'}
            className="shrink-0 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
            onClick={(event) => {
                event.stopPropagation();
                toggle();
            }}
        >
            <Icon
                className="size-4"
                style={{ color: current?.color }}
                strokeWidth={done ? 2.5 : 2}
            />
        </button>
    );
}

export function MemberAvatar({
    member,
    className,
}: {
    member: Pick<Member, 'name' | 'avatar'>;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar className={cn('size-6', className)} title={member.name}>
            {member.avatar && (
                <AvatarImage src={member.avatar} alt={member.name} />
            )}
            <AvatarFallback className="bg-primary/10 text-[10px] font-semibold text-primary">
                {getInitials(member.name)}
            </AvatarFallback>
        </Avatar>
    );
}

export function AssigneePicker({
    task,
    showName = false,
}: {
    task: Task;
    showName?: boolean;
}) {
    return (
        <AssigneeMenu
            assigneeId={task.assignee_id}
            showName={showName}
            onChange={(assigneeId) =>
                updateTask(task, { assignee_id: assigneeId })
            }
        />
    );
}

/** Pick a workspace member (or nobody); used by tasks and subtasks. */
export function AssigneeMenu({
    assigneeId,
    onChange,
    showName = false,
    size = 'size-6',
}: {
    assigneeId: number | null;
    onChange: (assigneeId: number | null) => void;
    showName?: boolean;
    size?: string;
}) {
    const { members, canEdit } = useProject();
    const assignee = members.find((member) => member.id === assigneeId);

    if (!canEdit) {
        return assignee ? (
            <span className="flex items-center gap-2 text-sm">
                <MemberAvatar member={assignee} className={size} />
                {showName && <span className="truncate">{assignee.name}</span>}
            </span>
        ) : (
            showName && (
                <span className="text-sm text-muted-foreground">
                    Unassigned
                </span>
            )
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="flex items-center gap-2 rounded text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onClick={(event) => event.stopPropagation()}
            >
                {assignee ? (
                    <>
                        <MemberAvatar member={assignee} className={size} />
                        {showName && (
                            <span className="truncate">{assignee.name}</span>
                        )}
                    </>
                ) : (
                    <>
                        <span
                            className={cn(
                                'flex items-center justify-center rounded-full border border-dashed border-muted-foreground/50 text-muted-foreground',
                                size,
                            )}
                        >
                            <UserPlus className="size-3" />
                        </span>
                        {showName && (
                            <span className="text-muted-foreground">
                                Unassigned
                            </span>
                        )}
                    </>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-56"
                onClick={(event) => event.stopPropagation()}
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Assignee
                </DropdownMenuLabel>
                {members.map((member) => (
                    <DropdownMenuItem
                        key={member.id}
                        onSelect={() => onChange(member.id)}
                    >
                        <MemberAvatar member={member} className="size-5" />
                        <span className="truncate">{member.name}</span>
                        {member.id === assigneeId && (
                            <Check className="ml-auto" />
                        )}
                    </DropdownMenuItem>
                ))}
                {assignee && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => onChange(null)}>
                            <X /> Unassign
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function PriorityFlag({
    priority,
    className,
}: {
    priority: Priority | null;
    className?: string;
}) {
    return (
        <Flag
            className={cn('size-4', className)}
            style={{ color: priority ? PRIORITY_COLORS[priority] : undefined }}
            fill={priority ? PRIORITY_COLORS[priority] : 'none'}
        />
    );
}

export function PriorityPicker({
    task,
    showLabel = false,
}: {
    task: Task;
    showLabel?: boolean;
}) {
    const { priorities, canEdit } = useProject();
    const current = priorities.find((p) => p.value === task.priority);

    if (!canEdit) {
        return current || showLabel ? (
            <span
                className={cn(
                    'flex items-center gap-2 text-sm',
                    !current && 'text-muted-foreground/60',
                )}
            >
                <PriorityFlag priority={task.priority} />
                {current?.label ?? 'None'}
            </span>
        ) : null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'flex items-center gap-2 rounded text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    !current && 'text-muted-foreground/60',
                )}
                onClick={(event) => event.stopPropagation()}
            >
                <PriorityFlag priority={task.priority} />
                {(showLabel || current) && (
                    <span className={cn(!showLabel && 'hidden sm:inline')}>
                        {current?.label ?? 'None'}
                    </span>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-44"
                onClick={(event) => event.stopPropagation()}
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Priority
                </DropdownMenuLabel>
                {priorities.map((priority) => (
                    <DropdownMenuItem
                        key={priority.value}
                        onSelect={() =>
                            updateTask(task, { priority: priority.value })
                        }
                    >
                        <PriorityFlag priority={priority.value} />
                        {priority.label}
                        {priority.value === task.priority && (
                            <Check className="ml-auto" />
                        )}
                    </DropdownMenuItem>
                ))}
                {current && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() =>
                                updateTask(task, { priority: null })
                            }
                        >
                            <X /> Clear
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function DueDatePicker({ task }: { task: Task }) {
    return (
        <DueDateButton
            date={task.due_date}
            overdue={isOverdue(task)}
            onChange={(date) => updateTask(task, { due_date: date })}
        />
    );
}

/** A calendar icon (plus the date, once set) that opens the native date picker. */
export function DueDateButton({
    date,
    overdue,
    onChange,
    label = 'Due date',
}: {
    date: string | null;
    overdue: boolean;
    onChange: (date: string | null) => void;
    label?: string;
}) {
    const { canEdit } = useProject();
    const input = useRef<HTMLInputElement>(null);

    if (!canEdit) {
        return date ? (
            <span
                className={cn(
                    'flex items-center gap-1.5 text-sm whitespace-nowrap',
                    overdue && 'text-red-500',
                )}
            >
                <CalendarDays className="size-4" />
                {formatDue(date)}
            </span>
        ) : null;
    }

    return (
        <span className="relative inline-flex">
            <button
                type="button"
                className={cn(
                    'flex items-center gap-1.5 rounded text-sm whitespace-nowrap outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    !date && 'text-muted-foreground/60',
                    overdue && 'text-red-500',
                )}
                onClick={(event) => {
                    event.stopPropagation();
                    input.current?.showPicker();
                }}
            >
                <CalendarDays className="size-4" />
                {date && formatDue(date)}
            </button>
            <input
                ref={input}
                type="date"
                tabIndex={-1}
                aria-label={label}
                className="pointer-events-none absolute inset-0 opacity-0"
                value={date ?? ''}
                onClick={(event) => event.stopPropagation()}
                onChange={(event) => onChange(event.target.value || null)}
            />
        </span>
    );
}
