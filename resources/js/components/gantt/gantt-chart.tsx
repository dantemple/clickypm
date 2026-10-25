import { Gantt, Willow, WillowDark } from '@svar-ui/react-gantt';
import type { IApi, ITask } from '@svar-ui/react-gantt';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useAppearance } from '@/hooks/use-appearance';
import { formatDue, updateTask } from '@/lib/tasks';
import type { GanttZoom, Task, TaskDependency, TaskInput } from '@/types';
import '@svar-ui/react-gantt/style.css';

/** A row that groups tasks on the chart, such as a project on the timeline. */
export type GanttGroup = {
    id: number;
    name: string;
    color: string;
};

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/** The timescale and column width at each zoom level. */
const ZOOM_LEVELS: Record<
    GanttZoom,
    {
        cellWidth: number;
        scales: {
            unit: string;
            step: number;
            format: string | ((date: Date) => string);
        }[];
    }
> = {
    day: {
        cellWidth: 36,
        scales: [
            { unit: 'month', step: 1, format: '%F %Y' },
            { unit: 'day', step: 1, format: '%j' },
        ],
    },
    week: {
        cellWidth: 150,
        scales: [
            { unit: 'month', step: 1, format: '%F %Y' },
            {
                unit: 'week',
                step: 1,
                format: (date: Date) =>
                    `${date.getDate()} ${MONTHS[date.getMonth()]}`,
            },
        ],
    },
    month: {
        cellWidth: 140,
        scales: [
            { unit: 'year', step: 1, format: '%Y' },
            { unit: 'month', step: 1, format: '%M' },
        ],
    },
    year: {
        cellWidth: 60,
        scales: [
            { unit: 'year', step: 1, format: '%Y' },
            {
                unit: 'quarter',
                step: 1,
                format: (date: Date) =>
                    `Q${Math.floor(date.getMonth() / 3) + 1}`,
            },
        ],
    },
};

/** The grid beside the chart: name, start and due date. */
const COLUMNS = [
    { id: 'text', header: 'Task', flexgrow: 1 },
    {
        id: 'start',
        header: 'Start',
        width: 90,
        align: 'center' as const,
        template: (value: unknown, row: ITask) =>
            row.type === 'summary'
                ? ''
                : value instanceof Date
                  ? formatDue(toDateString(value))
                  : '–',
    },
    {
        id: 'end',
        header: 'Due',
        width: 90,
        align: 'center' as const,
        template: (value: unknown, row: ITask) =>
            row.type === 'summary'
                ? ''
                : row.type === 'milestone' && row.start instanceof Date
                  ? formatDue(toDateString(row.start))
                  : value instanceof Date
                    ? formatDue(toDateString(addDays(value, -1)))
                    : '–',
    },
];

const DAY = 86_400_000;

/** "2026-10-24" as a local Date at midnight. */
function parseDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function toDateString(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
}

/** Whole days from one local midnight to another, ignoring DST shifts. */
function daysBetween(from: Date, to: Date): number {
    return Math.round((to.getTime() - from.getTime()) / DAY);
}

type DatedTask = Task & { due_date: string };

/** The id a group row has on the chart; tasks keep their own ids. */
const groupRowId = (group: GanttGroup) => `group-${group.id}`;

/**
 * A task as the chart draws it. Bars run from the start date (or the due
 * date, if there's no start) to the due date; milestones sit on the due
 * date. Done tasks are full whatever their progress says.
 */
function toGanttTask(task: DatedTask, parent?: string): ITask {
    const base = {
        id: task.id,
        text: task.title,
        progress: task.completed_at ? 100 : task.progress,
        ...(parent ? { parent } : {}),
    };

    const due = parseDate(task.due_date);

    if (task.is_milestone) {
        return { ...base, type: 'milestone', start: due };
    }

    return {
        ...base,
        type: 'task',
        start: task.start_date ? parseDate(task.start_date) : due,
        // The chart stores the end as the morning after the last day.
        end: addDays(due, 1),
    };
}

/** A group row spanning its tasks, with the tasks nested under it. */
function toGroupRows(group: GanttGroup, tasks: DatedTask[]): ITask[] {
    const starts = tasks.map((task) =>
        parseDate(task.start_date ?? task.due_date),
    );
    const ends = tasks.map((task) => addDays(parseDate(task.due_date), 1));

    return [
        {
            id: groupRowId(group),
            text: group.name,
            type: 'summary',
            open: true,
            start: new Date(Math.min(...starts.map((d) => d.getTime()))),
            end: new Date(Math.max(...ends.map((d) => d.getTime()))),
            progress: 0,
        },
        ...tasks.map((task) => toGanttTask(task, groupRowId(group))),
    ];
}

function byStart(a: DatedTask, b: DatedTask): number {
    return (
        (a.start_date ?? a.due_date).localeCompare(
            b.start_date ?? b.due_date,
        ) || a.position - b.position
    );
}

/** The chart's timescale geometry, enough to place a date on it. */
type ScaleGeometry = {
    start: Date;
    end: Date;
    width: number;
};

/**
 * The Gantt chart used by a project's Gantt view and the workspace
 * timeline. Tasks without a due date can't be drawn and are counted in a
 * note instead. Dragging a bar, a bar's edge or its progress handle saves
 * the task; drawing a link between bars or deleting one calls back.
 */
export function GanttChart({
    tasks,
    groups,
    dependencies,
    zoom: zoomLevel,
    readonly,
    onOpenTask,
    onLink,
    onUnlink,
}: {
    tasks: Task[];
    groups?: GanttGroup[];
    dependencies: TaskDependency[];
    zoom: GanttZoom;
    readonly: boolean;
    onOpenTask: (task: Task) => void;
    onLink?: (predecessor: Task, successor: Task) => void;
    onUnlink?: (dependency: TaskDependency) => void;
}) {
    const { resolvedAppearance } = useAppearance();
    const zoom = ZOOM_LEVELS[zoomLevel];
    const wrapper = useRef<HTMLDivElement>(null);
    const [scales, setScales] = useState<ScaleGeometry | null>(null);
    const [area, setArea] = useState<Element | null>(null);
    const unsubscribe = useRef<() => void>(() => {});

    // The chart's own markers don't get positioned in this release, so the
    // "today" line is drawn here, inside the chart's scrolling area.
    useEffect(() => {
        setArea(wrapper.current?.querySelector('.wx-area') ?? null);
    }, [scales]);

    useEffect(() => () => unsubscribe.current(), []);

    // The chart's handlers are attached once; these refs keep them current.
    const latest = useRef({
        tasksById: new Map<number, Task>(),
        dependenciesById: new Map<number, TaskDependency>(),
        onOpenTask,
        onLink,
        onUnlink,
    });
    latest.current = {
        tasksById: new Map(tasks.map((task) => [task.id, task])),
        dependenciesById: new Map(dependencies.map((d) => [d.id, d])),
        onOpenTask,
        onLink,
        onUnlink,
    };

    // The chart resets itself when its props change, so keep them stable.
    const { ganttTasks, unscheduledCount, emptyGroups } = useMemo(() => {
        const dated = tasks
            .filter((task): task is DatedTask => !!task.due_date)
            .sort(byStart);

        if (!groups) {
            return {
                ganttTasks: dated.map((task) => toGanttTask(task)),
                unscheduledCount: tasks.length - dated.length,
                emptyGroups: 0,
            };
        }

        const rows: ITask[] = [];
        let emptyGroups = 0;

        for (const group of groups) {
            const own = dated.filter((task) => task.project_id === group.id);

            if (own.length === 0) {
                emptyGroups++;

                continue;
            }

            rows.push(...toGroupRows(group, own));
        }

        return {
            ganttTasks: rows,
            unscheduledCount: tasks.length - dated.length,
            emptyGroups,
        };
    }, [tasks, groups]);

    const links = useMemo(() => {
        const drawn = new Set(
            ganttTasks
                .map((task) => task.id)
                .filter((id) => typeof id === 'number'),
        );

        return dependencies
            .filter(
                (d) => drawn.has(d.predecessor_id) && drawn.has(d.successor_id),
            )
            .map((d) => ({
                id: d.id,
                source: d.predecessor_id,
                target: d.successor_id,
                type: 'e2s',
            }));
    }, [dependencies, ganttTasks]);
    const columns = useMemo(() => COLUMNS, []);

    const init = useCallback((api: IApi) => {
        unsubscribe.current();
        const scalesState = api.getReactiveState()._scales;

        if (scalesState) {
            unsubscribe.current = scalesState.subscribe(
                (value: ScaleGeometry | undefined) =>
                    setScales((current) =>
                        value &&
                        current &&
                        current.start.getTime() === value.start.getTime() &&
                        current.end.getTime() === value.end.getTime() &&
                        current.width === value.width
                            ? current
                            : value
                              ? {
                                    start: value.start,
                                    end: value.end,
                                    width: value.width,
                                }
                              : null,
                    ),
            );
        }

        // Tasks are made, moved between groups and removed elsewhere.
        for (const action of [
            'add-task',
            'delete-task',
            'copy-task',
            'move-task',
            'indent-task',
            'update-link',
        ]) {
            api.intercept(action, () => false);
        }

        // Drawing a link between two bars asks the server to save it; the
        // chart gets the saved link back through its props.
        api.intercept(
            'add-link',
            (ev: {
                link: { source?: number | string; target?: number | string };
            }) => {
                const { tasksById, onLink } = latest.current;
                const predecessor = tasksById.get(Number(ev.link.source ?? 0));
                const successor = tasksById.get(Number(ev.link.target ?? 0));

                if (predecessor && successor && onLink) {
                    onLink(predecessor, successor);
                }

                return false;
            },
        );

        api.intercept('delete-link', (ev: { id: number | string }) => {
            const { dependenciesById, onUnlink } = latest.current;
            const dependency = dependenciesById.get(Number(ev.id));

            if (dependency && onUnlink) {
                onUnlink(dependency);
            }

            return false;
        });

        // Double-clicking opens the task panel instead of the chart's editor.
        api.intercept('show-editor', (ev: { id: number | string }) => {
            const task = latest.current.tasksById.get(Number(ev.id));

            if (task) {
                latest.current.onOpenTask(task);
            }

            return false;
        });

        // Dragging a bar, its edge or its progress handle saves on drop.
        api.on(
            'update-task',
            (ev: { id: number | string; inProgress?: boolean }) => {
                if (ev.inProgress) {
                    return;
                }

                const task = latest.current.tasksById.get(Number(ev.id));
                const updated = api.getTask(ev.id);

                if (!task || !updated.start) {
                    return;
                }

                const start = toDateString(updated.start);
                const due =
                    updated.type === 'milestone' || !updated.end
                        ? start
                        : toDateString(addDays(updated.end, -1));
                const changes: TaskInput = {};

                if (due !== task.due_date) {
                    changes.due_date = due;
                }

                // A bar with no start of its own only gets one once it's
                // stretched; moving it just moves the due date.
                if (
                    !task.is_milestone &&
                    (task.start_date || start !== due) &&
                    start !== (task.start_date ?? task.due_date)
                ) {
                    changes.start_date = start;
                }

                const progress = Math.round(updated.progress ?? task.progress);

                if (!task.completed_at && progress !== task.progress) {
                    changes.progress = progress;
                }

                if (Object.keys(changes).length > 0) {
                    updateTask(task, changes);
                }
            },
        );
    }, []);

    const Theme = resolvedAppearance === 'dark' ? WillowDark : Willow;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayLeft = scales
        ? (daysBetween(scales.start, today) * scales.width) /
          daysBetween(scales.start, scales.end)
        : null;
    const showToday =
        todayLeft !== null &&
        scales !== null &&
        todayLeft >= 0 &&
        todayLeft <= scales.width;

    const notes = [
        unscheduledCount > 0 &&
            `${unscheduledCount} ${unscheduledCount === 1 ? 'task has' : 'tasks have'} no due date, so ${unscheduledCount === 1 ? "it isn't" : "they aren't"} on the chart.`,
        emptyGroups > 0 &&
            `${emptyGroups} ${emptyGroups === 1 ? 'project has' : 'projects have'} no dated tasks yet.`,
    ].filter(Boolean);

    return (
        <div className="flex h-full min-h-0 flex-col gap-2">
            {notes.length > 0 && (
                <p className="text-xs text-muted-foreground print:hidden">
                    {notes.join(' ')} Set dates from the task panel or the List
                    view.
                </p>
            )}
            <div
                ref={wrapper}
                className="gantt-print min-h-0 flex-1 overflow-hidden rounded-lg border"
            >
                <Theme>
                    <Gantt
                        key={zoomLevel}
                        tasks={ganttTasks}
                        links={links}
                        scales={zoom.scales}
                        cellWidth={zoom.cellWidth}
                        cellHeight={36}
                        inclusiveEnd
                        readonly={readonly}
                        columns={columns}
                        init={init}
                    />
                </Theme>
                {area &&
                    showToday &&
                    createPortal(
                        <div
                            className="gantt-today"
                            style={{ left: `${todayLeft}px` }}
                            title="Today"
                        />,
                        area,
                    )}
            </div>
        </div>
    );
}
