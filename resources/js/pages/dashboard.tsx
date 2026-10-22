import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, FolderPlus } from 'lucide-react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import CreateProjectModal from '@/components/projects/create-project-modal';
import { PriorityFlag, StatusBadge } from '@/components/tasks/pickers';
import { Button } from '@/components/ui/button';
import { formatDue, isOverdue } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as showProject } from '@/routes/projects';
import type { DashboardInvitation, DashboardTask, ProjectStats } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    projectStats: ProjectStats[];
    myTasks: DashboardTask[];
};

export default function Dashboard({
    pendingInvitations = [],
    projectStats,
    myTasks,
}: Props) {
    const { auth, currentTeam } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Hi, {auth.user.name.split(' ')[0]}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Here’s what’s happening in {currentTeam?.name}.
                    </p>
                </div>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">Projects</h2>
                        {currentTeam?.role !== 'client' && (
                            <CreateProjectModal>
                                <Button variant="outline" size="sm">
                                    <FolderPlus /> New project
                                </Button>
                            </CreateProjectModal>
                        )}
                    </div>

                    {projectStats.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            No projects yet. Create one to start adding tasks.
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {projectStats.map((project) => (
                                <ProjectCard
                                    key={project.id}
                                    project={project}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold">
                        Assigned to me{' '}
                        <span className="font-normal text-muted-foreground">
                            {myTasks.length}
                        </span>
                    </h2>

                    {myTasks.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Nothing open is assigned to you.
                        </div>
                    ) : (
                        <ul className="divide-y overflow-hidden rounded-xl border">
                            {myTasks.map((task) => (
                                <li key={task.id}>
                                    <Link
                                        href={showProject.url({
                                            project: task.project.id,
                                        })}
                                        className="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-muted/50"
                                    >
                                        <StatusBadge
                                            status={task.status}
                                            className="hidden sm:inline-flex"
                                        />
                                        <span className="min-w-0 flex-1 truncate">
                                            {task.title}
                                        </span>
                                        <span className="hidden items-center gap-1.5 text-xs text-muted-foreground md:flex">
                                            <span
                                                className="size-2.5 rounded-sm"
                                                style={{
                                                    backgroundColor:
                                                        task.project.color,
                                                }}
                                            />
                                            {task.project.name}
                                        </span>
                                        {task.priority && (
                                            <PriorityFlag
                                                priority={task.priority}
                                            />
                                        )}
                                        {task.due_date && (
                                            <span
                                                className={cn(
                                                    'flex w-24 items-center justify-end gap-1 text-xs whitespace-nowrap text-muted-foreground',
                                                    isOverdue(task) &&
                                                        'text-red-500',
                                                )}
                                            >
                                                <CalendarDays className="size-3.5" />
                                                {formatDue(task.due_date)}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function ProjectCard({ project }: { project: ProjectStats }) {
    const percent =
        project.tasksCount === 0
            ? 0
            : Math.round((project.doneTasksCount / project.tasksCount) * 100);

    return (
        <Link
            href={showProject.url({ project: project.id })}
            className="group flex flex-col gap-4 rounded-xl border p-4 transition-colors hover:bg-muted/40"
        >
            <div className="flex items-center gap-3">
                <span
                    className="size-8 shrink-0 rounded-lg"
                    style={{ backgroundColor: project.color }}
                />
                <div className="min-w-0">
                    <h3 className="truncate font-medium">{project.name}</h3>
                    <p className="truncate text-xs text-muted-foreground">
                        {project.description || 'No description'}
                    </p>
                </div>
            </div>
            <div className="space-y-1.5">
                <div className="flex justify-between text-xs text-muted-foreground">
                    <span>
                        {project.doneTasksCount} of {project.tasksCount} done
                        {project.requestsCount > 0 && (
                            <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                {project.requestsCount}{' '}
                                {project.requestsCount === 1
                                    ? 'request'
                                    : 'requests'}
                            </span>
                        )}
                    </span>
                    <span>{percent}%</span>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full rounded-full"
                        style={{
                            width: `${percent}%`,
                            backgroundColor: project.color,
                        }}
                    />
                </div>
            </div>
        </Link>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
