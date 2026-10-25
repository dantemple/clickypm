import { usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Lock, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { MemberAvatar, StatusBadge } from '@/components/tasks/pickers';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    addComment,
    deleteComment,
    formatDateTime,
    formatDue,
} from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Activity, Comment, Task } from '@/types';

type Entry =
    | { kind: 'comment'; at: string; comment: Comment }
    | { kind: 'activity'; at: string; activity: Activity };

/** The timeline as it's shown: comments, with the changes between them folded. */
type Item =
    | { kind: 'comment'; comment: Comment }
    | { kind: 'changes'; key: string; changes: Activity[] };

/**
 * The task's history: comments in the order they were posted, with the
 * changes made between them folded into one line each. The comment box
 * lives outside this, pinned to the bottom of the panel.
 */
export function TaskTimeline({
    task,
    comments,
    activity,
}: {
    task: Task;
    comments: Comment[];
    activity: Activity[];
}) {
    const [expanded, setExpanded] = useState<Set<string>>(new Set());

    const entries: Entry[] = [
        ...comments.map((comment) => ({
            kind: 'comment' as const,
            at: comment.created_at,
            comment,
        })),
        ...activity.map((item) => ({
            kind: 'activity' as const,
            at: item.created_at,
            activity: item,
        })),
    ].sort((a, b) => a.at.localeCompare(b.at));

    const items = entries.reduce<Item[]>((items, entry) => {
        const last = items.at(-1);

        if (entry.kind === 'comment') {
            items.push({ kind: 'comment', comment: entry.comment });
        } else if (last?.kind === 'changes') {
            last.changes.push(entry.activity);
        } else {
            items.push({
                kind: 'changes',
                key: `a${entry.activity.id}`,
                changes: [entry.activity],
            });
        }

        return items;
    }, []);

    const toggle = (key: string) =>
        setExpanded((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });

    return (
        <section className="flex flex-col gap-3 border-t px-4 py-3">
            <h3 className="text-sm font-medium">
                Activity{' '}
                <span className="font-normal text-muted-foreground">
                    · {comments.length}{' '}
                    {comments.length === 1 ? 'comment' : 'comments'}
                </span>
            </h3>

            {comments.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    No comments yet.
                </p>
            )}

            <ul className="flex flex-col gap-2">
                {items.map((item) =>
                    item.kind === 'comment' ? (
                        <CommentItem
                            key={`c${item.comment.id}`}
                            task={task}
                            comment={item.comment}
                        />
                    ) : (
                        <ChangesItem
                            key={item.key}
                            changes={item.changes}
                            expanded={expanded.has(item.key)}
                            onToggle={() => toggle(item.key)}
                        />
                    ),
                )}
            </ul>
        </section>
    );
}

/** A run of changes between two comments, folded to one line until opened. */
function ChangesItem({
    changes,
    expanded,
    onToggle,
}: {
    changes: Activity[];
    expanded: boolean;
    onToggle: () => void;
}) {
    const Chevron = expanded ? ChevronUp : ChevronDown;
    const last = changes[changes.length - 1];

    return (
        <li className="flex flex-col gap-1">
            <button
                type="button"
                className="flex items-center gap-2 rounded px-2 py-0.5 text-left text-xs text-muted-foreground hover:text-foreground"
                onClick={onToggle}
            >
                <span className="size-1.5 shrink-0 rounded-full bg-muted-foreground/40" />
                {changes.length} {changes.length === 1 ? 'change' : 'changes'}
                <span className="whitespace-nowrap">
                    · {formatDateTime(last.created_at)}
                </span>
                <Chevron className="size-3.5" />
            </button>
            {expanded && (
                <ul className="flex flex-col gap-1">
                    {changes.map((activity) => (
                        <ActivityItem key={activity.id} activity={activity} />
                    ))}
                </ul>
            )}
        </li>
    );
}

function ActivityItem({ activity }: { activity: Activity }) {
    return (
        <li className="flex items-start gap-2 px-2 text-xs text-muted-foreground">
            <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-muted-foreground/40" />
            <span className="min-w-0 flex-1">
                <span className="font-medium text-foreground">
                    {activity.user?.name ?? 'Someone'}
                </span>{' '}
                {describeActivity(activity)}
                <span className="whitespace-nowrap">
                    {' '}
                    · {formatDateTime(activity.created_at)}
                </span>
            </span>
        </li>
    );
}

/** Activity data is free-form JSON; only ever print strings from it. */
const text = (value: unknown, fallback = ''): string =>
    typeof value === 'string' ? value : fallback;

const quote = (value: unknown) => (
    <q className="text-foreground">{text(value)}</q>
);

function describeActivity({ type, data }: Activity): ReactNode {
    const status = (value: unknown) => {
        const status = value as { name: string; color: string } | null;

        return status ? (
            <StatusBadge
                status={status}
                className="mx-0.5 h-5 px-1.5 text-[10px]"
            />
        ) : (
            'a deleted status'
        );
    };

    switch (type) {
        case 'created':
            return 'created this task';
        case 'requested':
            return 'requested this task';
        case 'accepted':
            return 'accepted the request';
        case 'restored':
            return 'restored this task from the trash';
        case 'status':
            return (
                <>
                    moved this from {status(data.from)} to {status(data.to)}
                </>
            );
        case 'assigned':
            return <>assigned this to {text(data.name, 'someone')}</>;
        case 'unassigned':
            return <>unassigned {text(data.name, 'someone')}</>;
        case 'priority':
            return data.to ? (
                <>set the priority to {text(data.to)}</>
            ) : (
                'cleared the priority'
            );
        case 'due_date':
            return data.to ? (
                <>set the due date to {formatDue(text(data.to))}</>
            ) : (
                'removed the due date'
            );
        case 'start_date':
            return data.to ? (
                <>set the start date to {formatDue(text(data.to))}</>
            ) : (
                'removed the start date'
            );
        case 'milestone':
            return 'made this a milestone';
        case 'not_milestone':
            return 'made this a normal task again';
        case 'progress':
            return (
                <>
                    set progress to {typeof data.to === 'number' ? data.to : 0}%
                </>
            );
        case 'blocked_by_added':
            return <>made this wait for {quote(data.title)}</>;
        case 'blocked_by_removed':
            return <>stopped this waiting for {quote(data.title)}</>;
        case 'blocks_added':
            return <>made {quote(data.title)} wait for this</>;
        case 'blocks_removed':
            return <>stopped {quote(data.title)} waiting for this</>;
        case 'renamed':
            return <>renamed this from {quote(data.from)}</>;
        case 'description':
            return 'updated the description';
        case 'subtask_added':
            return <>added the subtask {quote(data.title)}</>;
        case 'subtask_completed':
            return <>completed {quote(data.title)}</>;
        case 'subtask_reopened':
            return <>reopened {quote(data.title)}</>;
        case 'subtask_removed':
            return <>removed the subtask {quote(data.title)}</>;
        case 'attachment_added':
            return <>attached {quote(data.name)}</>;
        case 'attachment_removed':
            return <>removed the attachment {quote(data.name)}</>;
        case 'custom_field':
            return describeFieldChange(data);
        default:
            return 'updated this task';
    }
}

function describeFieldChange(data: Activity['data']): ReactNode {
    const name = <span className="text-foreground">{text(data.name)}</span>;
    const value = (value: unknown) => {
        if (data.type === 'date') {
            return <q className="text-foreground">{formatDue(text(value))}</q>;
        }

        if (data.type === 'number' && text(value) !== '') {
            return (
                <q className="text-foreground">
                    {Number(text(value)).toLocaleString('en-GB', {
                        maximumFractionDigits: 10,
                    })}
                </q>
            );
        }

        return quote(value);
    };

    if (data.type === 'checkbox') {
        return data.to ? <>checked {name}</> : <>unchecked {name}</>;
    }

    if (!data.to) {
        return <>cleared {name}</>;
    }

    return data.from ? (
        <>
            changed {name} from {value(data.from)} to {value(data.to)}
        </>
    ) : (
        <>
            set {name} to {value(data.to)}
        </>
    );
}

function CommentItem({ task, comment }: { task: Task; comment: Comment }) {
    const { auth } = usePage().props;
    const { members } = useProject();
    const author = comment.user?.name ?? 'Deleted user';
    const isClient =
        members.find((member) => member.id === comment.user?.id)?.isClient ??
        false;

    return (
        <li
            className={cn(
                'group flex gap-3 rounded-md p-2',
                comment.is_internal &&
                    'bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/30',
            )}
        >
            <MemberAvatar member={{ name: author }} className="mt-0.5 size-7" />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 text-xs">
                    <span className="font-medium">{author}</span>
                    {isClient && (
                        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground uppercase">
                            Client
                        </span>
                    )}
                    {comment.is_internal && (
                        <span className="flex items-center gap-1 font-medium text-amber-700 dark:text-amber-400">
                            <Lock className="size-3" /> Internal note
                        </span>
                    )}
                    <span className="text-muted-foreground">
                        {formatDateTime(comment.created_at)}
                    </span>
                    {comment.user?.id === auth.user.id && (
                        <button
                            type="button"
                            title="Delete comment"
                            className="ml-auto rounded p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive focus-visible:opacity-100"
                            onClick={() =>
                                confirm('Delete this comment?') &&
                                deleteComment(task, comment)
                            }
                        >
                            <Trash2 className="size-3.5" />
                        </button>
                    )}
                </div>
                <p className="mt-1 text-sm whitespace-pre-wrap">
                    {comment.body}
                </p>
            </div>
        </li>
    );
}

/** The comment box. Pinned to the bottom of the task panel. */
export function CommentForm({ task }: { task: Task }) {
    const { canEdit } = useProject();
    const [body, setBody] = useState('');
    const [internal, setInternal] = useState(false);
    const [sending, setSending] = useState(false);

    const submit = () => {
        if (body.trim() === '' || sending) {
            return;
        }

        setSending(true);
        addComment(
            task,
            { body: body.trim(), is_internal: internal },
            {
                onSuccess: () => {
                    setBody('');
                    setInternal(false);
                },
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <form
            className={cn(
                'flex flex-col gap-2 rounded-md border p-2 focus-within:ring-2 focus-within:ring-ring',
                internal &&
                    'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10',
            )}
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
        >
            <textarea
                value={body}
                rows={2}
                aria-label="Comment"
                placeholder={
                    internal
                        ? 'Internal note — only your team can see this'
                        : 'Write a comment… (⌘/Ctrl + Enter to send)'
                }
                className="resize-y bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                onChange={(event) => setBody(event.target.value)}
                onKeyDown={(event) => {
                    if (
                        event.key === 'Enter' &&
                        (event.metaKey || event.ctrlKey)
                    ) {
                        event.preventDefault();
                        submit();
                    }
                }}
            />
            <div className="flex items-center gap-3">
                {canEdit && (
                    <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Checkbox
                            checked={internal}
                            onCheckedChange={(checked) =>
                                setInternal(checked === true)
                            }
                        />
                        <Lock className="size-3" /> Internal note
                    </label>
                )}
                <Button
                    type="submit"
                    size="sm"
                    className="ml-auto"
                    disabled={body.trim() === '' || sending}
                >
                    {internal ? 'Add note' : 'Comment'}
                </Button>
            </div>
        </form>
    );
}
