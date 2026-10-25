import { Head, router } from '@inertiajs/react';
import { Suspense, lazy, useEffect, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { addDependency, removeDependency } from '@/lib/tasks';
import { timeline } from '@/routes';
import { show as showProject } from '@/routes/projects';
import type { GanttZoom, ProjectSummary, Task, TaskDependency } from '@/types';

// The chart library is only loaded on the pages that draw it.
const GanttChart = lazy(() =>
    import('@/components/gantt/gantt-chart').then((module) => ({
        default: module.GanttChart,
    })),
);

type Props = {
    projects: ProjectSummary[];
    tasks: Task[];
    dependencies: TaskDependency[];
    canEdit: boolean;
};

/** Every project in the workspace on one chart, grouped by project. */
export default function Timeline({
    projects,
    tasks,
    dependencies,
    canEdit,
}: Props) {
    const [zoom, setZoom] = useState<GanttZoom>('month');
    // Client-only: server rendering can't wait for the lazy chunk.
    const [mounted, setMounted] = useState(false);
    useEffect(() => setMounted(true), []);

    const openTask = (task: Task) =>
        router.visit(
            showProject.url(
                { project: task.project_id },
                { query: { task: task.id } },
            ),
        );

    return (
        <>
            <Head title="Timeline" />

            <div className="flex h-[calc(100vh-5rem)] min-h-96 flex-col gap-3 p-4">
                <div className="flex flex-wrap items-center gap-3 print:hidden">
                    <div>
                        <h1 className="text-xl font-semibold">Timeline</h1>
                        <p className="text-sm text-muted-foreground">
                            Every project's dated tasks on one chart.
                            Double-click a bar to open the task.
                        </p>
                    </div>
                    <ToggleGroup
                        type="single"
                        size="sm"
                        variant="outline"
                        className="ml-auto"
                        value={zoom}
                        onValueChange={(value) =>
                            value && setZoom(value as GanttZoom)
                        }
                        aria-label="Zoom"
                    >
                        <ToggleGroupItem value="week">Week</ToggleGroupItem>
                        <ToggleGroupItem value="month">Month</ToggleGroupItem>
                        <ToggleGroupItem value="year">Year</ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <div className="min-h-0 flex-1">
                    {mounted ? (
                        <Suspense fallback={<Loading />}>
                            <GanttChart
                                tasks={tasks}
                                groups={projects}
                                dependencies={dependencies}
                                zoom={zoom}
                                readonly={!canEdit}
                                onOpenTask={openTask}
                                onLink={(predecessor, successor) =>
                                    addDependency(successor, predecessor.id)
                                }
                                onUnlink={(dependency) => {
                                    const task = tasks.find(
                                        (t) => t.id === dependency.successor_id,
                                    );

                                    if (task) {
                                        removeDependency(
                                            task.project_id,
                                            dependency,
                                        );
                                    }
                                }}
                            />
                        </Suspense>
                    ) : (
                        <Loading />
                    )}
                </div>
            </div>
        </>
    );
}

function Loading() {
    return (
        <div className="flex justify-center p-8">
            <Spinner />
        </div>
    );
}

Timeline.layout = {
    breadcrumbs: [{ title: 'Timeline', href: timeline() }],
};
