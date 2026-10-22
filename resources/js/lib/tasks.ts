import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    accept as acceptTaskRoute,
    destroy as destroyTaskRoute,
    move as moveTaskRoute,
    store as storeTaskRoute,
    update as updateTaskRoute,
} from '@/routes/projects/tasks';
import {
    destroy as destroyCommentRoute,
    store as storeCommentRoute,
} from '@/routes/projects/tasks/comments';
import { restore as restoreTrashedTaskRoute } from '@/routes/trash/tasks';
import {
    destroy as destroyAttachmentRoute,
    store as storeAttachmentRoute,
} from '@/routes/projects/tasks/attachments';
import {
    destroy as destroySubtaskRoute,
    store as storeSubtaskRoute,
    update as updateSubtaskRoute,
} from '@/routes/projects/tasks/subtasks';
import type {
    Attachment,
    Comment,
    Priority,
    Subtask,
    SubtaskInput,
    Task,
    TaskInput,
} from '@/types';

// A fixed locale keeps server-rendered dates identical to the client's.
const DATE_LOCALE = 'en-GB';

export const PRIORITY_COLORS: Record<Priority, string> = {
    1: '#ef4444',
    2: '#f59e0b',
    3: '#3b82f6',
    4: '#9ca3af',
};

export const PROJECT_COLORS = [
    '#7c3aed',
    '#3b82f6',
    '#0ea5e9',
    '#14b8a6',
    '#22c55e',
    '#eab308',
    '#f97316',
    '#ef4444',
    '#ec4899',
    '#64748b',
];

const visitOptions = {
    preserveScroll: true,
    preserveState: true,
    onError: (errors: Record<string, string>) => {
        toast.error(Object.values(errors)[0] ?? 'Something went wrong');
    },
};

export function createTask(
    projectId: number,
    data: TaskInput & { title: string; attachments?: File[] },
    callbacks: { onSuccess?: () => void; onFinish?: () => void } = {},
): void {
    router.post(storeTaskRoute.url({ project: projectId }), data, {
        ...visitOptions,
        ...callbacks,
    });
}

export function updateTask(task: Task, data: TaskInput): void {
    router.patch(
        updateTaskRoute.url({ project: task.project_id, task: task.id }),
        data,
        visitOptions,
    );
}

/** Put a task in a status column; `order` is that column's ids, top to bottom. */
export function moveTask(
    task: Task,
    statusId: number,
    order: number[],
    onFinish?: () => void,
): void {
    router.post(
        moveTaskRoute.url({ project: task.project_id, task: task.id }),
        { status_id: statusId, order },
        { ...visitOptions, onFinish },
    );
}

/**
 * Move a task to the trash. No confirmation needed: the toast offers Undo,
 * and the task stays in the workspace's Trash for 30 days either way.
 */
export function deleteTask(
    task: Task,
    { message, onSuccess }: { message: string; onSuccess?: () => void },
): void {
    router.delete(
        destroyTaskRoute.url({ project: task.project_id, task: task.id }),
        {
            ...visitOptions,
            onSuccess: () => {
                onSuccess?.();
                toast.success(message, {
                    description: task.title,
                    duration: 8000,
                    action: {
                        label: 'Undo',
                        onClick: () =>
                            router.post(
                                restoreTrashedTaskRoute.url({ task: task.id }),
                                {},
                                visitOptions,
                            ),
                    },
                });
            },
        },
    );
}

export function acceptTask(task: Task): void {
    router.post(
        acceptTaskRoute.url({ project: task.project_id, task: task.id }),
        {},
        visitOptions,
    );
}

export function addComment(
    task: Task,
    data: { body: string; is_internal: boolean },
    callbacks: { onSuccess?: () => void; onFinish?: () => void } = {},
): void {
    router.post(
        storeCommentRoute.url({ project: task.project_id, task: task.id }),
        data,
        { ...visitOptions, ...callbacks },
    );
}

export function deleteComment(task: Task, comment: Comment): void {
    router.delete(
        destroyCommentRoute.url({
            project: task.project_id,
            task: task.id,
            comment: comment.id,
        }),
        visitOptions,
    );
}

export function createSubtask(
    task: Task,
    title: string,
    onSuccess?: () => void,
): void {
    router.post(
        storeSubtaskRoute.url({ project: task.project_id, task: task.id }),
        { title },
        { ...visitOptions, onSuccess },
    );
}

export function updateSubtask(
    task: Task,
    subtask: Subtask,
    data: SubtaskInput,
): void {
    router.patch(
        updateSubtaskRoute.url({
            project: task.project_id,
            task: task.id,
            subtask: subtask.id,
        }),
        data,
        visitOptions,
    );
}

export function deleteSubtask(task: Task, subtask: Subtask): void {
    router.delete(
        destroySubtaskRoute.url({
            project: task.project_id,
            task: task.id,
            subtask: subtask.id,
        }),
        visitOptions,
    );
}

export function uploadAttachment(
    task: Task,
    file: File,
    callbacks: {
        onProgress?: (percent: number) => void;
        onFinish?: () => void;
    } = {},
): void {
    router.post(
        storeAttachmentRoute.url({ project: task.project_id, task: task.id }),
        { files: [file] },
        {
            ...visitOptions,
            forceFormData: true,
            onProgress: (event) =>
                callbacks.onProgress?.(event?.percentage ?? 0),
            onFinish: callbacks.onFinish,
        },
    );
}

export function deleteAttachment(task: Task, attachment: Attachment): void {
    router.delete(
        destroyAttachmentRoute.url({
            project: task.project_id,
            task: task.id,
            attachment: attachment.id,
        }),
        visitOptions,
    );
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Today's date as YYYY-MM-DD in the browser's timezone. */
export function todayString(): string {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function parseDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function isOverdue(task: Task): boolean {
    return (
        !!task.due_date && !task.completed_at && task.due_date < todayString()
    );
}

/** Short, human due date: Today, Tomorrow, Yesterday, or "24 Oct". */
export function formatDue(date: string): string {
    const days = Math.round(
        (parseDate(date).getTime() - parseDate(todayString()).getTime()) /
            86_400_000,
    );

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Tomorrow';
    }

    if (days === -1) {
        return 'Yesterday';
    }

    const parsed = parseDate(date);

    return parsed.toLocaleDateString(DATE_LOCALE, {
        day: 'numeric',
        month: 'short',
        year:
            parsed.getFullYear() === new Date().getFullYear()
                ? undefined
                : 'numeric',
    });
}

export function formatDateTime(date: string): string {
    return new Date(date).toLocaleString(DATE_LOCALE, {
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function matchesSearch(task: Task, search: string): boolean {
    const term = search.trim().toLowerCase();

    return (
        term === '' ||
        task.title.toLowerCase().includes(term) ||
        (task.description ?? '').toLowerCase().includes(term)
    );
}
