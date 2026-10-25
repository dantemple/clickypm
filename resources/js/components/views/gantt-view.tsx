import { GanttChart } from '@/components/gantt/gantt-chart';
import { useProject } from '@/components/projects/project-context';
import { addDependency, removeDependency } from '@/lib/tasks';
import type { Task, TaskDependency, ViewSettings } from '@/types';

/** A project's Gantt view: its tasks on the shared chart, saving into it. */
export function GanttView({
    tasks,
    dependencies,
    settings,
}: {
    tasks: Task[];
    dependencies: TaskDependency[];
    settings: ViewSettings;
}) {
    const { project, canEdit, openTask } = useProject();

    return (
        <div className="h-[calc(100vh-15rem)] min-h-96 p-4">
            <GanttChart
                tasks={tasks}
                dependencies={dependencies}
                zoom={settings.zoom ?? 'week'}
                readonly={!canEdit}
                onOpenTask={openTask}
                onLink={(predecessor, successor) =>
                    addDependency(successor, predecessor.id)
                }
                onUnlink={(dependency) =>
                    removeDependency(project.id, dependency)
                }
            />
        </div>
    );
}
