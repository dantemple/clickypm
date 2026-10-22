import { router } from '@inertiajs/react';
import {
    fieldForColumn,
    fieldSortValue,
    fieldValue,
} from '@/lib/custom-fields';
import { update as updateViewRoute } from '@/routes/projects/views';
import type {
    CustomField,
    Member,
    ProjectView,
    SortColumn,
    SortDirection,
    Status,
    Task,
    ViewSettings,
} from '@/types';

export function saveViewSettings(
    projectId: number,
    view: ProjectView,
    settings: Partial<ViewSettings>,
): void {
    router.patch(
        updateViewRoute.url({ project: projectId, view: view.id }),
        { settings },
        { preserveScroll: true, preserveState: true },
    );
}

export function renameView(
    projectId: number,
    view: ProjectView,
    name: string,
): void {
    router.patch(
        updateViewRoute.url({ project: projectId, view: view.id }),
        { name },
        { preserveScroll: true, preserveState: true },
    );
}

/** Compare two possibly-empty values, always sorting empties last. */
function compareNullable<T>(
    a: T | null,
    b: T | null,
    compare: (a: T, b: T) => number,
    direction: SortDirection,
): number {
    if (a === null && b === null) {
        return 0;
    }

    if (a === null) {
        return 1;
    }

    if (b === null) {
        return -1;
    }

    return direction === 'asc' ? compare(a, b) : -compare(a, b);
}

export function sortTasks(
    tasks: Task[],
    column: SortColumn,
    direction: SortDirection,
    statuses: Status[],
    members: Member[],
    fields: CustomField[] = [],
): Task[] {
    const statusIndex = new Map(statuses.map((s, index) => [s.id, index]));
    const memberName = new Map(members.map((m) => [m.id, m.name]));
    const byNumber = (a: number, b: number) => a - b;
    const byString = (a: string, b: string) => a.localeCompare(b);
    const field = fieldForColumn(column, fields);

    const value = (task: Task): string | number | null => {
        if (field) {
            return fieldSortValue(field, fieldValue(task, field));
        }

        switch (column) {
            case 'position':
                return task.position;
            case 'title':
                return task.title.toLowerCase();
            case 'status':
                return statusIndex.get(task.status_id) ?? null;
            case 'assignee':
                return task.assignee_id
                    ? (memberName.get(task.assignee_id) ?? null)
                    : null;
            case 'priority':
                return task.priority;
            case 'due_date':
                return task.due_date;
            case 'created_at':
                return task.created_at;
            default:
                // A column for a field the project no longer uses.
                return null;
        }
    };

    return [...tasks].sort((a, b) => {
        const [va, vb] = [value(a), value(b)];
        const result =
            typeof va === 'number' || typeof vb === 'number'
                ? compareNullable(
                      va as number | null,
                      vb as number | null,
                      byNumber,
                      direction,
                  )
                : compareNullable(
                      va as string | null,
                      vb as string | null,
                      byString,
                      direction,
                  );

        return result || a.position - b.position;
    });
}
