import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArchiveRestore } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/tasks';
import { archived as archivedIndex } from '@/routes';
import { show as showProject, unarchive } from '@/routes/projects';
import type { ProjectSummary } from '@/types';

type ArchivedProject = ProjectSummary & {
    description: string | null;
    tasksCount: number;
    archivedBy: string | null;
    archivedAt: string | null;
};

type Props = {
    archivedProjects: ArchivedProject[];
    canUnarchive: boolean;
};

export default function ArchivedProjects({
    archivedProjects: projects,
    canUnarchive,
}: Props) {
    return (
        <>
            <Head title="Archived projects" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Archived projects
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Finished projects kept out of the way. They stay
                        readable, and unarchiving brings them back everywhere.
                    </p>
                </div>

                {projects.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                        <Archive className="mx-auto mb-2 size-6" />
                        No archived projects.
                    </div>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-xl border">
                        {projects.map((project) => (
                            <li
                                key={project.id}
                                className="flex flex-wrap items-center gap-3 px-4 py-3"
                            >
                                <span
                                    className="size-3 shrink-0 rounded-sm"
                                    style={{ backgroundColor: project.color }}
                                />
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={showProject.url({
                                            project: project.id,
                                        })}
                                        className="font-medium hover:underline"
                                    >
                                        {project.name}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {project.tasksCount}{' '}
                                        {project.tasksCount === 1
                                            ? 'task'
                                            : 'tasks'}
                                        {project.archivedAt &&
                                            ` · archived ${formatDateTime(project.archivedAt)}`}
                                        {project.archivedBy &&
                                            ` by ${project.archivedBy}`}
                                    </p>
                                </div>
                                {canUnarchive && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                unarchive.url({
                                                    project: project.id,
                                                }),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <ArchiveRestore /> Unarchive
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ArchivedProjects.layout = {
    breadcrumbs: [{ title: 'Archived projects', href: archivedIndex() }],
};
