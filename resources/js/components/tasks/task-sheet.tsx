import {
    Check,
    Inbox,
    Link2,
    MoreHorizontal,
    Paperclip,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { CustomFieldInput } from '@/components/custom-fields/custom-field-input';
import { useProject } from '@/components/projects/project-context';
import {
    AssigneePicker,
    DueDatePicker,
    PriorityPicker,
    StatusPicker,
} from '@/components/tasks/pickers';
import {
    TaskAttachments,
    useAttachmentUpload,
} from '@/components/tasks/task-attachments';
import { TaskSubtasks } from '@/components/tasks/task-subtasks';
import { CommentForm, TaskTimeline } from '@/components/tasks/task-timeline';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useClipboard } from '@/hooks/use-clipboard';
import { useSyncedDraft } from '@/hooks/use-synced-draft';
import {
    acceptTask,
    deleteTask,
    formatDateTime,
    updateTask,
} from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { show as showProject } from '@/routes/projects';
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
            <SheetContent className="w-full gap-0 overflow-hidden sm:max-w-xl">
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

/**
 * The panel is a header, a scrolling body and the comment box pinned at the
 * bottom. Sections only appear once they have something in them; the add
 * row at the end of the body is where empty ones start.
 */
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
    const [, copy] = useClipboard();
    const upload = useAttachmentUpload(task);
    const [addingSubtask, setAddingSubtask] = useState(false);
    // Follows other people's edits, except while you're typing in it.
    const {
        draft: title,
        setDraft: setTitle,
        focusProps: titleFocus,
    } = useSyncedDraft(task.title);

    const showSubtasks = details.subtasks.length > 0 || addingSubtask;
    const showAttachments =
        details.attachments.length > 0 || upload.progress !== null;

    const saveTitle = () => {
        const trimmed = title.trim();

        if (trimmed === '') {
            setTitle(task.title);
        } else if (trimmed !== task.title) {
            updateTask(task, { title: trimmed });
        }
    };

    const remove = (message: string) =>
        deleteTask(task, { message, onSuccess: onClose });

    const copyLink = async () => {
        const path = showProject.url(
            { project: project.id },
            { query: { task: task.id } },
        );

        if (await copy(`${window.location.origin}${path}`)) {
            toast.success('Link copied');
        } else {
            toast.error('Could not copy the link');
        }
    };

    return (
        <>
            <SheetHeader className="gap-1 border-b pr-20">
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
                <p className="text-xs text-muted-foreground">
                    Created {formatDateTime(task.created_at)}
                    {task.created_by && ` by ${task.created_by.name}`}
                    {task.completed_at &&
                        ` · Completed ${formatDateTime(task.completed_at)}`}
                </p>
            </SheetHeader>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Task actions"
                        className="absolute top-2.5 right-10 size-7"
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={copyLink}>
                        <Link2 /> Copy link
                    </DropdownMenuItem>
                    {canEdit && !task.needs_triage && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() =>
                                    remove('Task moved to the trash')
                                }
                            >
                                <Trash2 /> Delete
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <div
                className={cn(
                    'flex flex-1 flex-col overflow-y-auto transition-colors',
                    upload.dragging &&
                        'bg-primary/5 ring-2 ring-primary/40 ring-inset',
                )}
                {...upload.dragProps}
            >
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
                                <Button
                                    size="sm"
                                    onClick={() => acceptTask(task)}
                                >
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

                <dl className="grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-x-4 gap-y-1.5 px-4 py-3 text-sm">
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
                    <dd>
                        <DueDatePicker task={task} placeholder="Empty" />
                    </dd>

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

                <Description task={task} />

                {showSubtasks && (
                    <TaskSubtasks
                        task={task}
                        subtasks={details.subtasks}
                        autoFocus={addingSubtask}
                    />
                )}

                {showAttachments && (
                    <TaskAttachments
                        task={task}
                        attachments={details.attachments}
                        progress={upload.progress}
                    />
                )}

                <div className="flex flex-wrap gap-1 border-t px-3 py-2">
                    {canEdit && !showSubtasks && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground"
                            onClick={() => setAddingSubtask(true)}
                        >
                            <Plus /> Subtask
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground"
                        disabled={upload.progress !== null}
                        onClick={upload.openPicker}
                    >
                        <Paperclip /> Attach file
                    </Button>
                    {upload.input}
                </div>

                <TaskTimeline
                    task={task}
                    comments={details.comments}
                    activity={details.activity}
                />
            </div>

            <div className="border-t bg-background p-3">
                <CommentForm task={task} />
            </div>
        </>
    );
}

/** Reads as text; click to edit, blur to save. */
function Description({ task }: { task: Task }) {
    const { canEdit } = useProject();
    const [editing, setEditing] = useState(false);
    const {
        draft: description,
        setDraft: setDescription,
        focusProps,
    } = useSyncedDraft(task.description ?? '');

    const save = () => {
        if (description !== (task.description ?? '')) {
            updateTask(task, { description: description || null });
        }
    };

    return (
        <section className="flex flex-col gap-1 border-t px-4 py-3">
            <h3 className="text-sm font-medium">Description</h3>

            {!canEdit ? (
                <p className="text-sm whitespace-pre-wrap">
                    {task.description || (
                        <span className="text-muted-foreground">
                            No description.
                        </span>
                    )}
                </p>
            ) : editing ? (
                <textarea
                    autoFocus
                    value={description}
                    aria-label="Description"
                    placeholder="Add more detail…"
                    className="field-sizing-content min-h-24 resize-y rounded-md border bg-transparent p-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    onChange={(event) => setDescription(event.target.value)}
                    onFocus={focusProps.onFocus}
                    onBlur={() => {
                        focusProps.onBlur();
                        save();
                        setEditing(false);
                    }}
                />
            ) : (
                <button
                    type="button"
                    className="-mx-2 rounded-md px-2 py-1 text-left text-sm whitespace-pre-wrap hover:bg-muted/50"
                    onClick={() => setEditing(true)}
                >
                    {task.description || (
                        <span className="text-muted-foreground">
                            Add a description…
                        </span>
                    )}
                </button>
            )}
        </section>
    );
}
