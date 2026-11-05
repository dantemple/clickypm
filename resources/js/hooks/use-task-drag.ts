import {
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    DragEndEvent,
    DragOverEvent,
    DragStartEvent,
    UniqueIdentifier,
} from '@dnd-kit/core';
import { arrayMove, sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { moveTask } from '@/lib/tasks';
import type { Task } from '@/types';

/** Task ids per status id, top to bottom. */
export type StatusColumns = Record<number, number[]>;

const STATUS_PREFIX = 'status-';

/**
 * The droppable id for a whole status (a board column or a list group), so a
 * task can be dropped on empty space in it, not just on another task.
 */
export function statusDropId(statusId: number): string {
    return `${STATUS_PREFIX}${statusId}`;
}

/**
 * Drag tasks between a project's statuses and reorder them within one.
 *
 * Render each status as a droppable with `statusDropId` wrapping a
 * SortableContext of its task ids, and give the DndContext `sensors` and
 * `handlers`. `columns` reflects the drag in progress, so rows make room
 * as the task passes over them and don't jump back after a drop.
 */
export function useTaskDrag(tasks: Task[]) {
    const { statuses } = useProject();
    const tasksById = new Map(tasks.map((task) => [task.id, task]));

    // Tasks arrive sorted by position, so each status keeps that order.
    const fromProps: StatusColumns = Object.fromEntries(
        statuses.map((status) => [
            status.id,
            tasks
                .filter((task) => task.status_id === status.id)
                .map((task) => task.id),
        ]),
    );

    // While dragging, and until the server confirms a drop, show the local
    // arrangement so tasks don't jump back to where they started.
    const [pending, setPending] = useState<StatusColumns | null>(null);
    const [activeId, setActiveId] = useState<number | null>(null);
    const columns = pending ?? fromProps;

    const sensors = useSensors(
        // A small distance keeps plain clicks (to open a task) from starting a drag.
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const statusOf = (id: UniqueIdentifier): number | undefined => {
        if (typeof id === 'string') {
            return id.startsWith(STATUS_PREFIX)
                ? Number(id.slice(STATUS_PREFIX.length))
                : undefined;
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

    // Moving between statuses happens while hovering, so the task makes
    // room in the status it's over; reordering within one is on drop.
    const onDragOver = ({ active, over }: DragOverEvent) => {
        const from = statusOf(active.id);
        const to = over ? statusOf(over.id) : undefined;

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
        const statusId = statusOf(active.id);

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

    const onDragCancel = () => {
        setActiveId(null);
        setPending(null);
    };

    return {
        columns,
        tasksById,
        activeTask: activeId ? tasksById.get(activeId) : undefined,
        sensors,
        handlers: { onDragStart, onDragOver, onDragEnd, onDragCancel },
    };
}
