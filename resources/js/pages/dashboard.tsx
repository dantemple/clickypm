import { Head, Link, usePage } from '@inertiajs/react';
import { FolderPlus } from 'lucide-react';
import { useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import CreateProjectModal from '@/components/projects/create-project-modal';
import { PriorityFlag, StatusBadge } from '@/components/tasks/pickers';
import { Button } from '@/components/ui/button';
import { formatDue, isOverdue, todayString } from '@/lib/tasks';
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
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {projectStats.map((project) => (
                                <ProjectCard
                                    key={project.id}
                                    project={project}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <AssignedToMe tasks={myTasks} />
            </div>
        </>
    );
}

function AssignedToMe({ tasks }: { tasks: DashboardTask[] }) {
    const [filter, setFilter] = useState<'all' | 'today'>('all');
    const today = todayString();
    const dueToday = tasks.filter((task) => task.due_date === today);
    const visible = filter === 'today' ? dueToday : tasks;

    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <h2 className="font-semibold">Assigned to me</h2>
                {tasks.length > 0 && (
                    <div className="flex gap-1.5">
                        <FilterPill
                            active={filter === 'all'}
                            onClick={() => setFilter('all')}
                        >
                            All · {tasks.length}
                        </FilterPill>
                        <FilterPill
                            active={filter === 'today'}
                            onClick={() => setFilter('today')}
                        >
                            Due today · {dueToday.length}
                        </FilterPill>
                    </div>
                )}
            </div>

            {visible.length === 0 ? (
                <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {filter === 'today'
                        ? 'Nothing assigned to you is due today.'
                        : 'Nothing open is assigned to you.'}
                </div>
            ) : (
                <div className="space-y-2">
                    <div className="hidden grid-cols-[6rem_1fr_12rem_8rem] gap-4 px-4 text-xs font-medium tracking-wider text-muted-foreground uppercase md:grid">
                        <span>Due</span>
                        <span>Task</span>
                        <span>Project</span>
                        <span className="text-right">Status</span>
                    </div>
                    <ul className="space-y-2">
                        {visible.map((task) => (
                            <li key={task.id}>
                                <AssignedTaskCard task={task} today={today} />
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}

function FilterPill({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-full px-3 py-1 text-sm font-medium transition-colors',
                active
                    ? 'bg-foreground text-background'
                    : 'bg-muted text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function AssignedTaskCard({
    task,
    today,
}: {
    task: DashboardTask;
    today: string;
}) {
    const overdue = isOverdue(task);
    const dueToday = task.due_date === today;

    return (
        <Link
            href={showProject.url({ project: task.project.id })}
            className={cn(
                'grid grid-cols-[3.5rem_1fr_auto] items-center gap-3 rounded-xl border bg-card px-4 py-3.5 text-sm transition-colors hover:bg-muted/50 md:grid-cols-[6rem_1fr_12rem_8rem] md:gap-4',
                dueToday &&
                    'border-amber-200 bg-amber-50 hover:bg-amber-100/70 dark:border-amber-500/30 dark:bg-amber-500/10 dark:hover:bg-amber-500/15',
                overdue &&
                    'border-red-200 bg-red-50 hover:bg-red-100/70 dark:border-red-500/30 dark:bg-red-500/10 dark:hover:bg-red-500/15',
            )}
        >
            <span
                className={cn(
                    'text-muted-foreground',
                    dueToday &&
                        'font-semibold text-amber-700 dark:text-amber-400',
                    overdue && 'font-semibold text-red-600 dark:text-red-400',
                )}
            >
                {task.due_date ? formatDue(task.due_date) : '—'}
            </span>
            <span className="flex min-w-0 items-center gap-2">
                <span className="truncate font-medium">{task.title}</span>
                {task.priority && <PriorityFlag priority={task.priority} />}
            </span>
            <span className="hidden min-w-0 items-center gap-2 text-muted-foreground md:flex">
                <span
                    className="size-2.5 shrink-0 rounded-full"
                    style={{ backgroundColor: task.project.color }}
                />
                <span className="truncate">{task.project.name}</span>
            </span>
            <span className="flex justify-end">
                <StatusBadge status={task.status} />
            </span>
        </Link>
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
            className="group flex min-h-52 flex-col rounded-2xl bg-[color-mix(in_srgb,var(--project)_8%,transparent)] p-6 text-[color-mix(in_srgb,var(--project)_70%,black)] transition-colors hover:bg-[color-mix(in_srgb,var(--project)_14%,transparent)] dark:bg-[color-mix(in_srgb,var(--project)_14%,transparent)] dark:text-[color-mix(in_srgb,var(--project)_55%,white)] dark:hover:bg-[color-mix(in_srgb,var(--project)_20%,transparent)]"
            style={{ '--project': project.color } as CSSProperties}
        >
            <div className="flex items-start justify-between gap-3">
                <h3 className="truncate text-lg font-semibold">
                    {project.name}
                </h3>
                <span
                    className="mt-1.5 size-3 shrink-0 rounded-full"
                    style={{ backgroundColor: project.color }}
                />
            </div>

            <div className="flex flex-1 flex-col justify-center py-4">
                {project.tasksCount === 0 ? (
                    <div className="space-y-1 font-medium">
                        <p>No tasks yet.</p>
                        <p className="opacity-90 group-hover:underline">
                            Add the first one →
                        </p>
                    </div>
                ) : (
                    <>
                        <p className="text-5xl font-bold tracking-tight">
                            {percent}
                            <span className="ml-0.5 text-2xl font-semibold opacity-60">
                                %
                            </span>
                        </p>
                        <p className="mt-1 flex flex-wrap items-center gap-2 text-sm opacity-80">
                            {project.doneTasksCount} of {project.tasksCount}{' '}
                            done
                            {project.requestsCount > 0 && (
                                <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                    {project.requestsCount}{' '}
                                    {project.requestsCount === 1
                                        ? 'request'
                                        : 'requests'}
                                </span>
                            )}
                        </p>
                    </>
                )}
            </div>

            <div className="h-1.5 overflow-hidden rounded-full bg-[color-mix(in_srgb,var(--project)_20%,transparent)]">
                <div
                    className="h-full rounded-full"
                    style={{
                        width: `${percent}%`,
                        backgroundColor: project.color,
                    }}
                />
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
