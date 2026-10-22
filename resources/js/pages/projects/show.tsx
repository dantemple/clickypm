import { Head, router } from '@inertiajs/react';
import {
    Columns3,
    Eye,
    EyeOff,
    Layers,
    ListChecks,
    MoreHorizontal,
    Plus,
    Search,
    Settings,
} from 'lucide-react';
import { useState } from 'react';
import { ProjectContext } from '@/components/projects/project-context';
import { ProjectSettingsDialog } from '@/components/projects/project-settings-dialog';
import { StatusesDialog } from '@/components/projects/statuses-dialog';
import { ViewTabs } from '@/components/projects/view-tabs';
import { RequestsInbox } from '@/components/tasks/requests-inbox';
import { TaskSheet } from '@/components/tasks/task-sheet';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { BoardView } from '@/components/views/board-view';
import { ListView } from '@/components/views/list-view';
import { TABLE_COLUMNS, TableView } from '@/components/views/table-view';
import { createTask, formatFileSize, matchesSearch } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { saveViewSettings } from '@/lib/views';
import { show as showProject } from '@/routes/projects';
import { show as showView } from '@/routes/projects/views';
import type {
    Activity,
    Attachment,
    Comment,
    GroupBy,
    Member,
    PriorityOption,
    Project,
    ProjectView,
    Status,
    Subtask,
    Task,
    TaskColumn,
    ViewSettings,
} from '@/types';

type Props = {
    project: Project;
    canEdit: boolean;
    views: ProjectView[];
    view: ProjectView;
    statuses: Status[];
    tasks: Task[];
    requests: Task[];
    openTaskId: number | null;
    comments: Comment[];
    subtasks: Subtask[];
    attachments: Attachment[];
    activity: Activity[];
    members: Member[];
    priorities: PriorityOption[];
    maxUploadMegabytes: number;
};

const GROUP_LABELS: Record<GroupBy, string> = {
    status: 'Status',
    priority: 'Priority',
    assignee: 'Assignee',
};

export default function ProjectShow({
    project,
    canEdit,
    views,
    view,
    statuses,
    tasks,
    requests,
    openTaskId,
    comments,
    subtasks,
    attachments,
    activity,
    members,
    priorities,
    maxUploadMegabytes,
}: Props) {
    const [search, setSearch] = useState('');
    const [selectedTaskId, setSelectedTaskId] = useState(openTaskId);
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [statusesOpen, setStatusesOpen] = useState(false);
    const [newTaskOpen, setNewTaskOpen] = useState(false);

    // Clients can't change the team's saved views, so their tweaks stay local.
    const [localSettings, setLocalSettings] = useState<{
        viewId: number;
        settings: Partial<ViewSettings>;
    } | null>(null);
    const settings: ViewSettings = {
        ...view.settings,
        ...(localSettings?.viewId === view.id ? localSettings.settings : {}),
    };
    const changeSettings = (changes: Partial<ViewSettings>) => {
        if (canEdit) {
            saveViewSettings(project.id, view, changes);

            return;
        }

        setLocalSettings({
            viewId: view.id,
            settings: {
                ...(localSettings?.viewId === view.id
                    ? localSettings.settings
                    : {}),
                ...changes,
            },
        });
    };

    const doneStatusIds = new Set(
        statuses.filter((status) => status.is_done).map((status) => status.id),
    );
    const visibleTasks = tasks.filter(
        (task) =>
            (settings.show_closed || !doneStatusIds.has(task.status_id)) &&
            matchesSearch(task, search),
    );
    const openTask =
        [...tasks, ...requests].find((task) => task.id === selectedTaskId) ??
        null;
    const closedCount = tasks.filter((task) =>
        doneStatusIds.has(task.status_id),
    ).length;

    // The open task lives in the URL (?task=) so it can be linked to, and so
    // the server sends that task's comments along with every page refresh.
    const selectTask = (taskId: number | null) => {
        setSelectedTaskId(taskId);
        router.get(
            showView.url(
                { project: project.id, view: view.id },
                taskId ? { query: { task: taskId } } : undefined,
            ),
            {},
            {
                only: [
                    'openTaskId',
                    'comments',
                    'subtasks',
                    'attachments',
                    'activity',
                ],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <ProjectContext.Provider
            value={{
                project,
                statuses,
                members,
                priorities,
                canEdit,
                openTask: (task) => selectTask(task.id),
            }}
        >
            <Head title={`${project.name} · ${view.name}`} />

            <div className="flex min-h-0 flex-1 flex-col">
                <header className="flex items-center gap-3 px-4 pt-4 pb-2">
                    <span
                        className="size-6 shrink-0 rounded-md"
                        style={{ backgroundColor: project.color }}
                    />
                    <div className="min-w-0">
                        <h1 className="flex items-center gap-2 truncate text-xl font-semibold">
                            {project.name}
                            {canEdit && project.hidden_from_clients && (
                                <span className="flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                                    <EyeOff className="size-3" /> Internal
                                </span>
                            )}
                        </h1>
                        {project.description && (
                            <p className="truncate text-sm text-muted-foreground">
                                {project.description}
                            </p>
                        )}
                    </div>
                    {canEdit && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label="Project options"
                                >
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuItem
                                    onSelect={() => setSettingsOpen(true)}
                                >
                                    <Settings /> Project settings
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => setStatusesOpen(true)}
                                >
                                    <ListChecks /> Edit statuses
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </header>

                <ViewTabs
                    project={project}
                    views={views}
                    current={view}
                    canEdit={canEdit}
                />

                <div className="flex flex-wrap items-center gap-2 border-b px-4 py-2">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            placeholder="Search tasks"
                            className="h-8 w-48 pl-8 sm:w-60"
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {view.type === 'list' && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Layers /> Group:{' '}
                                    {
                                        GROUP_LABELS[
                                            settings.group_by ?? 'status'
                                        ]
                                    }
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuLabel className="text-xs text-muted-foreground">
                                    Group by
                                </DropdownMenuLabel>
                                <DropdownMenuRadioGroup
                                    value={settings.group_by ?? 'status'}
                                    onValueChange={(value) =>
                                        changeSettings({
                                            group_by: value as GroupBy,
                                        })
                                    }
                                >
                                    {(
                                        Object.keys(GROUP_LABELS) as GroupBy[]
                                    ).map((key) => (
                                        <DropdownMenuRadioItem
                                            key={key}
                                            value={key}
                                        >
                                            {GROUP_LABELS[key]}
                                        </DropdownMenuRadioItem>
                                    ))}
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    {view.type === 'table' && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Columns3 /> Columns
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuLabel className="text-xs text-muted-foreground">
                                    Show columns
                                </DropdownMenuLabel>
                                {(
                                    Object.keys(TABLE_COLUMNS) as TaskColumn[]
                                ).map((column) => {
                                    const columns = settings.columns ?? [];

                                    return (
                                        <DropdownMenuCheckboxItem
                                            key={column}
                                            checked={columns.includes(column)}
                                            onSelect={(event) =>
                                                event.preventDefault()
                                            }
                                            onCheckedChange={(checked) =>
                                                changeSettings({
                                                    columns: checked
                                                        ? [...columns, column]
                                                        : columns.filter(
                                                              (c) =>
                                                                  c !== column,
                                                          ),
                                                })
                                            }
                                        >
                                            {TABLE_COLUMNS[column]}
                                        </DropdownMenuCheckboxItem>
                                    );
                                })}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    <Button
                        variant={settings.show_closed ? 'secondary' : 'outline'}
                        size="sm"
                        onClick={() =>
                            changeSettings({
                                show_closed: !settings.show_closed,
                            })
                        }
                    >
                        {settings.show_closed ? <Eye /> : <EyeOff />}
                        {settings.show_closed
                            ? 'Showing closed'
                            : `Closed hidden (${closedCount})`}
                    </Button>

                    <Button
                        size="sm"
                        className="ml-auto"
                        onClick={() => setNewTaskOpen(true)}
                    >
                        <Plus /> {canEdit ? 'Task' : 'Request a task'}
                    </Button>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto">
                    <RequestsInbox requests={requests} />
                    {view.type === 'list' && (
                        <ListView tasks={visibleTasks} settings={settings} />
                    )}
                    {view.type === 'board' && (
                        <BoardView tasks={visibleTasks} settings={settings} />
                    )}
                    {view.type === 'table' && (
                        <TableView
                            tasks={visibleTasks}
                            settings={settings}
                            onSettingsChange={changeSettings}
                        />
                    )}
                </div>
            </div>

            <TaskSheet
                task={openTask}
                details={
                    openTaskId === selectedTaskId
                        ? { comments, subtasks, attachments, activity }
                        : {
                              comments: [],
                              subtasks: [],
                              attachments: [],
                              activity: [],
                          }
                }
                onClose={() => selectTask(null)}
            />

            <NewTaskDialog
                projectId={project.id}
                isRequest={!canEdit}
                maxUploadMegabytes={maxUploadMegabytes}
                open={newTaskOpen}
                onOpenChange={setNewTaskOpen}
            />

            {canEdit && (
                <>
                    <ProjectSettingsDialog
                        project={project}
                        open={settingsOpen}
                        onOpenChange={setSettingsOpen}
                    />

                    <StatusesDialog
                        project={project}
                        statuses={statuses}
                        open={statusesOpen}
                        onOpenChange={setStatusesOpen}
                    />
                </>
            )}
        </ProjectContext.Provider>
    );
}

function NewTaskDialog({
    projectId,
    isRequest,
    maxUploadMegabytes,
    open,
    onOpenChange,
}: {
    projectId: number;
    isRequest: boolean;
    maxUploadMegabytes: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [processing, setProcessing] = useState(false);

    // Everything goes up in one request here, so cap the combined size.
    const totalBytes = files.reduce((sum, file) => sum + file.size, 0);
    const tooBig = totalBytes > maxUploadMegabytes * 1024 * 1024;

    const submit = () => {
        if (title.trim() === '' || tooBig) {
            return;
        }

        setProcessing(true);
        createTask(
            projectId,
            {
                title: title.trim(),
                description: description.trim() || null,
                ...(files.length > 0 ? { attachments: files } : {}),
            },
            {
                onSuccess: () => {
                    setTitle('');
                    setDescription('');
                    setFiles([]);
                    onOpenChange(false);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {isRequest ? 'Request a task' : 'New task'}
                        </DialogTitle>
                        <DialogDescription>
                            {isRequest
                                ? 'The team will review your request before it’s added to the project. You can follow up in the comments.'
                                : 'Added to the first open status. You can set the rest afterwards.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="new-task-title">Title</Label>
                        <Input
                            id="new-task-title"
                            autoFocus
                            value={title}
                            placeholder={
                                isRequest ? 'What do you need?' : 'Task name'
                            }
                            onChange={(event) => setTitle(event.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="new-task-description">
                            Details{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <textarea
                            id="new-task-description"
                            rows={4}
                            value={description}
                            className="rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            onChange={(event) =>
                                setDescription(event.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="new-task-files">
                            Attachments{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional, up to {maxUploadMegabytes} MB in
                                total)
                            </span>
                        </Label>
                        <Input
                            id="new-task-files"
                            type="file"
                            multiple
                            onChange={(event) =>
                                setFiles(Array.from(event.target.files ?? []))
                            }
                        />
                        {files.length > 0 && (
                            <p
                                className={cn(
                                    'text-xs text-muted-foreground',
                                    tooBig && 'text-destructive',
                                )}
                            >
                                {files.length}{' '}
                                {files.length === 1 ? 'file' : 'files'},{' '}
                                {formatFileSize(totalBytes)}
                                {tooBig &&
                                    ' — too large; attach the rest from the task once it’s created.'}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={
                                title.trim() === '' || tooBig || processing
                            }
                        >
                            {isRequest ? 'Send request' : 'Create task'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ProjectShow.layout = (props: {
    project: Project;
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: props.project.name,
            href: showProject({
                current_team: props.currentTeam?.slug,
                project: props.project.id,
            }),
        },
    ],
});
