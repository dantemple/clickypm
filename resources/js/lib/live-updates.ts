import { router } from '@inertiajs/react';
import { useEcho, useEchoNotification } from '@laravel/echo-react';

/** Props the sidebar and dashboard show for the current workspace. */
const WORKSPACE_PROPS = [
    'projects',
    'projectStats',
    'myTasks',
    'trashedProjects',
    'trashedTasks',
];

/** Props of the project page itself. */
const PROJECT_PROPS = [
    'project',
    'canEdit',
    'views',
    'view',
    'statuses',
    'tags',
    'customFields',
    'workspaceFields',
    'tasks',
    'requests',
    'dependencies',
    'members',
];

/** Props of the open task's panel. */
const TASK_PROPS = ['comments', 'subtasks', 'attachments', 'activity'];

/** Props the sidebar's notification bell shows. */
const NOTIFICATION_PROPS = ['unreadNotificationsCount'];

const pending = new Set<string>();
let timer: ReturnType<typeof setTimeout> | undefined;

/**
 * Re-fetch some page props shortly. Bursts of updates (a board move saves
 * several tasks) collapse into one request, which runs in the background so
 * it never cancels something you're saving.
 */
function reloadSoon(props: string[]): void {
    props.forEach((prop) => pending.add(prop));
    clearTimeout(timer);

    timer = setTimeout(() => {
        const only = [...pending];
        pending.clear();

        router.reload({ only, async: true, showProgress: false });
    }, 250);
}

/** Keep the sidebar and dashboard in step with the rest of the workspace. */
export function useWorkspaceLiveUpdates(teamId: number): void {
    useEcho(`workspaces.${teamId}`, '.workspace.changed', () =>
        reloadSoon(WORKSPACE_PROPS),
    );
}

/**
 * Keep a project page in step with everyone else's changes. The open task's
 * comments, subtasks and history are only re-fetched when it was touched.
 */
export function useProjectLiveUpdates(
    projectId: number,
    openTaskId: number | null,
): void {
    useEcho<{ taskIds: number[] }>(
        `projects.${projectId}`,
        '.project.changed',
        ({ taskIds }) => {
            const touchesOpenTask =
                openTaskId !== null &&
                (taskIds.length === 0 || taskIds.includes(openTaskId));

            reloadSoon(
                touchesOpenTask
                    ? [...PROJECT_PROPS, ...TASK_PROPS]
                    : PROJECT_PROPS,
            );
        },
        [openTaskId],
    );
}

/**
 * Keep the bell's unread count in step as notifications arrive on the
 * person's own channel. The callback runs too, so an open inbox can refresh.
 */
export function useNotificationLiveUpdates(
    userId: number,
    onNotification: () => void,
    dependencies: unknown[] = [],
): void {
    useEchoNotification(
        `App.Models.User.${userId}`,
        () => {
            reloadSoon(NOTIFICATION_PROPS);
            onNotification();
        },
        [],
        dependencies,
    );
}
