import { usePage } from '@inertiajs/react';
import { Check, Inbox, Trash2, X } from 'lucide-react';
import { Fragment } from 'react';
import { CustomFieldInput } from '@/components/custom-fields/custom-field-input';
import { useProject } from '@/components/projects/project-context';
import {
    AssigneePicker,
    DueDatePicker,
    PriorityPicker,
    StatusPicker,
} from '@/components/tasks/pickers';
import { TaskAttachments } from '@/components/tasks/task-attachments';
import { TaskSubtasks } from '@/components/tasks/task-subtasks';
import { TaskTimeline } from '@/components/tasks/task-timeline';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useSyncedDraft } from '@/hooks/use-synced-draft';
import {
    acceptTask,
    deleteTask,
    formatDateTime,
    formatDue,
    updateTask,
} from '@/lib/tasks';
import type { Activity, Attachment, Comment, Subtask, Task } from '@/types';

/** The open task's extra data, loaded from the server alongside ?task=. */
export type TaskDetailsData = {
    comments: Comment[];
    subtasks: Subtask[];
    attachments: Attachment[];
    activity: Activity[];
};

export function TaskSheet({
    task,
    details,
    onClose,
}: {
    task: Task | null;
    details: TaskDetailsData;
    onClose: () => void;
}) {
    return (
        <Sheet open={!!task} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-xl">
                {task && (
                    <TaskDetails
                        key={task.id}
                        task={task}
                        details={details}
                        onClose={onClose}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}

function TaskDetails({
    task,
    details,
    onClose,
}: {
    task: Task;
    details: TaskDetailsData;
    onClose: () => void;
}) {
    const { project, canEdit, customFields } = useProject();
    const ganttEnabled = !!usePage().props.currentTeam?.features?.gantt;
    // Both follow other people's edits, except while you're typing in them.
    const {
        draft: title,
        setDraft: setTitle,
        focusProps: titleFocus,
    } = useSyncedDraft(task.title);
    const {
        draft: description,
        setDraft: setDescription,
        focusProps: descriptionFocus,
    } = useSyncedDraft(task.description ?? '');

    const saveTitle = () => {
        const trimmed = title.trim();

        if (trimmed === '') {
            setTitle(task.title);
        } else if (trimmed !== task.title) {
            updateTask(task, { title: trimmed });
        }
    };

    const saveDescription = () => {
        if (description !== (task.description ?? '')) {
            updateTask(task, { description: description || null });
        }
    };

    const remove = (message: string) =>
        deleteTask(task, { message, onSuccess: onClose });

    return (
        <>
            <SheetHeader className="border-b pr-12">
                <SheetDescription className="flex items-center gap-2 text-xs">
                    <span
                        className="size-2.5 rounded-sm"
                        style={{ backgroundColor: project.color }}
                    />
                    {project.name}
                </SheetDescription>
                <SheetTitle className={canEdit ? 'sr-only' : 'text-xl'}>
                    {task.title}
                </SheetTitle>
                {canEdit && (
                    <textarea
                        value={title}
                        rows={1}
                        aria-label="Task name"
                        className="field-sizing-content resize-none bg-transparent text-xl font-semibold outline-none"
                        onChange={(event) => setTitle(event.target.value)}
                        onFocus={titleFocus.onFocus}
                        onBlur={() => {
                            titleFocus.onBlur();
                            saveTitle();
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                event.currentTarget.blur();
                            }
                        }}
                    />
                )}
            </SheetHeader>

            {task.needs_triage && (
                <div className="flex flex-wrap items-center gap-3 border-b bg-amber-50 px-4 py-3 text-sm dark:bg-amber-500/10">
                    <Inbox className="size-4 text-amber-600" />
                    <span className="flex-1">
                        {canEdit
                            ? `Client request from ${task.created_by?.name ?? 'a client'} — not on the project yet.`
                            : 'Awaiting review by the team.'}
                    </span>
                    {canEdit && (
                        <div className="flex gap-2">
                            <Button size="sm" onClick={() => acceptTask(task)}>
                                <Check /> Accept
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => remove('Request declined')}
                            >
                                <X /> Decline
                            </Button>
                        </div>
                    )}
                </div>
            )}

            <dl className="grid grid-cols-[7rem_1fr] items-center gap-x-4 gap-y-3 p-4 text-sm">
                <dt className="text-muted-foreground">Status</dt>
                <dd>
                    <StatusPicker task={task} />
                </dd>

                <dt className="text-muted-foreground">Assignee</dt>
                <dd>
                    <AssigneePicker task={task} showName />
                </dd>

                <dt className="text-muted-foreground">Priority</dt>
                <dd>
                    <PriorityPicker task={task} showLabel />
                </dd>

                <dt className="text-muted-foreground">Due date</dt>
                <dd className="flex items-center gap-2">
                    {canEdit ? (
                        <>
                            <Input
                                type="date"
                                className="h-8 w-44"
                                value={task.due_date ?? ''}
                                onChange={(event) =>
                                    updateTask(task, {
                                        due_date: event.target.value || null,
                                    })
                                }
                            />
                            {task.due_date && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        updateTask(task, { due_date: null })
                                    }
                                >
                                    Clear
                                </Button>
                            )}
                        </>
                    ) : task.due_date ? (
                        <DueDatePicker task={task} />
                    ) : (
                        <span className="text-muted-foreground">None</span>
                    )}
                </dd>

                {ganttEnabled && (
                    <>
                        <dt className="text-muted-foreground">Start date</dt>
                        <dd className="flex items-center gap-2">
                            {canEdit ? (
                                <>
                                    <Input
                                        type="date"
                                        aria-label="Start date"
                                        className="h-8 w-44"
                                        max={task.due_date ?? undefined}
                                        value={task.start_date ?? ''}
                                        onChange={(event) =>
                                            updateTask(task, {
                                                start_date:
                                                    event.target.value || null,
                                            })
                                        }
                                    />
                                    {task.start_date && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                updateTask(task, {
                                                    start_date: null,
                                                })
                                            }
                                        >
                                            Clear
                                        </Button>
                                    )}
                                </>
                            ) : task.start_date ? (
                                formatDue(task.start_date)
                            ) : (
                                <span className="text-muted-foreground">
                                    None
                                </span>
                            )}
                        </dd>

                        <dt className="text-muted-foreground">Milestone</dt>
                        <dd>
                            <Checkbox
                                aria-label="Milestone"
                                checked={task.is_milestone}
                                disabled={!canEdit}
                                onCheckedChange={(checked) =>
                                    updateTask(task, {
                                        is_milestone: checked === true,
                                    })
                                }
                            />
                        </dd>
                    </>
                )}

                {customFields.map((field) => (
                    <Fragment key={field.id}>
                        <dt
                            className="truncate text-muted-foreground"
                            title={field.name}
                        >
                            {field.name}
                        </dt>
                        <dd className="min-w-0">
                            <CustomFieldInput task={task} field={field} />
                        </dd>
                    </Fragment>
                ))}
            </dl>

            <div className="flex flex-col gap-2 border-t p-4">
                <label
                    htmlFor="task-description"
                    className="text-sm font-medium"
                >
                    Description
                </label>
                {canEdit ? (
                    <textarea
                        id="task-description"
                        value={description}
                        placeholder="Add more detail…"
                        className="min-h-32 resize-y rounded-md border bg-transparent p-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        onChange={(event) => setDescription(event.target.value)}
                        onFocus={descriptionFocus.onFocus}
                        onBlur={() => {
                            descriptionFocus.onBlur();
                            saveDescription();
                        }}
                    />
                ) : (
                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                        {task.description || 'No description.'}
                    </p>
                )}
            </div>

            <TaskSubtasks task={task} subtasks={details.subtasks} />

            <TaskAttachments task={task} attachments={details.attachments} />

            <TaskTimeline
                task={task}
                comments={details.comments}
                activity={details.activity}
            />

            <div className="mt-auto flex items-center justify-between gap-4 border-t p-4 text-xs text-muted-foreground">
                <span>
                    Created {formatDateTime(task.created_at)}
                    {task.created_by && ` by ${task.created_by.name}`}
                    {task.completed_at &&
                        ` · Completed ${formatDateTime(task.completed_at)}`}
                </span>
                {canEdit && !task.needs_triage && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        onClick={() => remove('Task moved to the trash')}
                    >
                        <Trash2 /> Delete
                    </Button>
                )}
            </div>
        </>
    );
}
