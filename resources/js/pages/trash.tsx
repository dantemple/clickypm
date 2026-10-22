import { Head, router } from '@inertiajs/react';
import { Inbox, RotateCcw, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/tasks';
import { index as trashIndex } from '@/routes/trash';
import {
    destroy as destroyProject,
    restore as restoreProject,
} from '@/routes/trash/projects';
import {
    destroy as destroyTask,
    restore as restoreTask,
} from '@/routes/trash/tasks';
import type { ProjectSummary } from '@/types';

type Trashed = {
    deletedBy: string | null;
    deletedAt: string;
    purgeAt: string;
};

type TrashedProject = ProjectSummary & Trashed & { tasksCount: number };

type TrashedTask = Trashed & {
    id: number;
    title: string;
    isRequest: boolean;
    project: ProjectSummary;
};

type Props = {
    trashedProjects: TrashedProject[];
    trashedTasks: TrashedTask[];
    canManageProjects: boolean;
    trashDays: number;
};

const visit = { preserveScroll: true };

function daysLeft(purgeAt: string): string {
    const days = Math.max(
        0,
        Math.ceil((new Date(purgeAt).getTime() - Date.now()) / 86_400_000),
    );

    return days === 0
        ? 'deleted for good today'
        : `deleted for good in ${days} ${days === 1 ? 'day' : 'days'}`;
}

export default function Trash({
    trashedProjects: projects,
    trashedTasks: tasks,
    canManageProjects,
    trashDays,
}: Props) {
    const empty = projects.length === 0 && tasks.length === 0;

    return (
        <>
            <Head title="Trash" />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Trash</h1>
                    <p className="text-sm text-muted-foreground">
                        Deleted projects and tasks stay here for {trashDays}{' '}
                        days, then they and their files are deleted for good.
                    </p>
                </div>

                {empty && (
                    <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                        <Trash2 className="mx-auto mb-2 size-6" />
                        The trash is empty.
                    </div>
                )}

                {projects.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">Projects</h2>
                        <ul className="divide-y overflow-hidden rounded-xl border">
                            {projects.map((project) => (
                                <TrashRow
                                    key={project.id}
                                    item={project}
                                    title={project.name}
                                    color={project.color}
                                    detail={`${project.tasksCount} ${project.tasksCount === 1 ? 'task' : 'tasks'}`}
                                    canManage={canManageProjects}
                                    onRestore={() =>
                                        router.post(
                                            restoreProject.url({
                                                project: project.id,
                                            }),
                                            {},
                                            visit,
                                        )
                                    }
                                    onDestroy={() =>
                                        confirm(
                                            `Delete “${project.name}”, its ${project.tasksCount} tasks and all of their files for good? This can't be undone.`,
                                        ) &&
                                        router.delete(
                                            destroyProject.url({
                                                project: project.id,
                                            }),
                                            visit,
                                        )
                                    }
                                />
                            ))}
                        </ul>
                        {!canManageProjects && (
                            <p className="text-xs text-muted-foreground">
                                Only workspace owners and admins can restore or
                                delete projects.
                            </p>
                        )}
                    </section>
                )}

                {tasks.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">Tasks</h2>
                        <ul className="divide-y overflow-hidden rounded-xl border">
                            {tasks.map((task) => (
                                <TrashRow
                                    key={task.id}
                                    item={task}
                                    title={task.title}
                                    color={task.project.color}
                                    detail={task.project.name}
                                    badge={task.isRequest ? 'Request' : null}
                                    canManage
                                    onRestore={() =>
                                        router.post(
                                            restoreTask.url({ task: task.id }),
                                            {},
                                            visit,
                                        )
                                    }
                                    onDestroy={() =>
                                        confirm(
                                            `Delete “${task.title}” and its files for good? This can't be undone.`,
                                        ) &&
                                        router.delete(
                                            destroyTask.url({ task: task.id }),
                                            visit,
                                        )
                                    }
                                />
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

function TrashRow({
    item,
    title,
    color,
    detail,
    badge = null,
    canManage,
    onRestore,
    onDestroy,
}: {
    item: Trashed;
    title: string;
    color: string;
    detail: string;
    badge?: string | null;
    canManage: boolean;
    onRestore: () => void;
    onDestroy: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
            <span
                className="size-3 shrink-0 rounded-sm"
                style={{ backgroundColor: color }}
            />
            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-2 truncate font-medium">
                    {title}
                    {badge && (
                        <span className="flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 uppercase dark:bg-amber-500/20 dark:text-amber-300">
                            <Inbox className="size-3" /> {badge}
                        </span>
                    )}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {detail} · Deleted {formatDateTime(item.deletedAt)}
                    {item.deletedBy && ` by ${item.deletedBy}`} ·{' '}
                    {daysLeft(item.purgeAt)}
                </p>
            </div>
            {canManage && (
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" onClick={onRestore}>
                        <RotateCcw /> Restore
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        onClick={onDestroy}
                    >
                        Delete for good
                    </Button>
                </div>
            )}
        </li>
    );
}

Trash.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Trash',
            href: trashIndex({ current_team: props.currentTeam?.slug }),
        },
    ],
});
