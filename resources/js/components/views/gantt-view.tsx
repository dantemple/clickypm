import { Gantt, Willow, WillowDark } from '@svar-ui/react-gantt';
import type { IApi, ITask } from '@svar-ui/react-gantt';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useProject } from '@/components/projects/project-context';
import { useAppearance } from '@/hooks/use-appearance';
import { formatDue, updateTask } from '@/lib/tasks';
import type { GanttZoom, Task, TaskInput, ViewSettings } from '@/types';
import '@svar-ui/react-gantt/style.css';

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
};

/** The grid beside the chart: name, start and due date. */
const COLUMNS = [
    { id: 'text', header: 'Task', flexgrow: 1 },
    {
        id: 'start',
        header: 'Start',
        width: 90,
        align: 'center' as const,
        template: (value: unknown) =>
            value instanceof Date ? formatDue(toDateString(value)) : '–',
    },
    {
        id: 'end',
        header: 'Due',
        width: 90,
        align: 'center' as const,
        template: (value: unknown, row: ITask) =>
            row.type === 'milestone' && row.start instanceof Date
                ? formatDue(toDateString(row.start))
                : value instanceof Date
                  ? formatDue(toDateString(addDays(value, -1)))
                  : '–',
    },
];

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

/**
 * A task as the chart draws it. Bars run from the start date (or the due
 * date, if there's no start) to the due date; milestones sit on the due
 * date. Tasks with no due date can't be drawn and are counted instead.
 */
function toGanttTask(task: Task & { due_date: string }): ITask {
    const base = {
        id: task.id,
        text: task.title,
        progress: task.completed_at ? 100 : 0,
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

/** The chart's timescale geometry, enough to place a date on it. */
type ScaleGeometry = {
    start: Date;
    end: Date;
    width: number;
};

const DAY = 86_400_000;

/** Whole days from one local midnight to another, ignoring DST shifts. */
function daysBetween(from: Date, to: Date): number {
    return Math.round((to.getTime() - from.getTime()) / DAY);
}

export function GanttView({
    tasks,
    settings,
}: {
    tasks: Task[];
    settings: ViewSettings;
}) {
    const { canEdit, openTask } = useProject();
    const { resolvedAppearance } = useAppearance();
    const zoom = ZOOM_LEVELS[settings.zoom ?? 'week'];
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
    const tasksById = useRef(new Map<number, Task>());
    tasksById.current = new Map(tasks.map((task) => [task.id, task]));
    const openTaskRef = useRef(openTask);
    openTaskRef.current = openTask;

    // The chart resets itself when its props change, so keep them stable.
    const ganttTasks = useMemo(
        () =>
            tasks
                .filter(
                    (task): task is Task & { due_date: string } =>
                        !!task.due_date,
                )
                .sort(
                    (a, b) =>
                        (a.start_date ?? a.due_date).localeCompare(
                            b.start_date ?? b.due_date,
                        ) || a.position - b.position,
                )
                .map(toGanttTask),
        [tasks],
    );
    const unscheduledCount = tasks.length - ganttTasks.length;
    const columns = useMemo(() => COLUMNS, []);
    const links = useMemo(() => [], []);

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

        // Tasks are made, linked and removed through the rest of the app.
        for (const action of [
            'add-task',
            'delete-task',
            'copy-task',
            'move-task',
            'indent-task',
            'add-link',
            'delete-link',
            'update-link',
        ]) {
            api.intercept(action, () => false);
        }

        // Double-clicking opens the task panel instead of the chart's editor.
        api.intercept('show-editor', (ev: { id: number | string }) => {
            const task = tasksById.current.get(Number(ev.id));

            if (task) {
                openTaskRef.current(task);
            }

            return false;
        });

        // Dragging or resizing a bar saves the new dates once it's dropped.
        api.on(
            'update-task',
            (ev: { id: number | string; inProgress?: boolean }) => {
                if (ev.inProgress) {
                    return;
                }

                const task = tasksById.current.get(Number(ev.id));
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

    return (
        <div className="flex h-[calc(100vh-15rem)] min-h-96 flex-col gap-2 p-4">
            {unscheduledCount > 0 && (
                <p className="text-xs text-muted-foreground">
                    {unscheduledCount}{' '}
                    {unscheduledCount === 1 ? 'task has' : 'tasks have'} no due
                    date, so{' '}
                    {unscheduledCount === 1 ? "it isn't" : "they aren't"} on the
                    chart. Set one from the task panel or the List view.
                </p>
            )}
            <div
                ref={wrapper}
                className="min-h-0 flex-1 overflow-hidden rounded-lg border"
            >
                <Theme>
                    <Gantt
                        key={settings.zoom ?? 'week'}
                        tasks={ganttTasks}
                        links={links}
                        scales={zoom.scales}
                        cellWidth={zoom.cellWidth}
                        cellHeight={36}
                        inclusiveEnd
                        readonly={!canEdit}
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
