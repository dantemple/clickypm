import { usePage } from '@inertiajs/react';
import { Download, FileText, Trash2, Upload } from 'lucide-react';
import type { DragEvent, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { useProject } from '@/components/projects/project-context';
import {
    deleteAttachment,
    formatDateTime,
    formatFileSize,
    uploadAttachment,
} from '@/lib/tasks';
import type { Attachment, Task } from '@/types';

export type UploadProgress = {
    name: string;
    percent: number;
    remaining: number;
};

/**
 * Everything needed to add files to a task: a hidden file input to render,
 * a way to open it, drag-and-drop handlers for a drop zone, and progress
 * while files go up.
 */
export function useAttachmentUpload(task: Task): {
    input: ReactNode;
    openPicker: () => void;
    dragging: boolean;
    dragProps: {
        onDragOver: (event: DragEvent) => void;
        onDragLeave: () => void;
        onDrop: (event: DragEvent) => void;
    };
    progress: UploadProgress | null;
} {
    const { maxUploadMegabytes } = usePage<{ maxUploadMegabytes: number }>()
        .props;
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [progress, setProgress] = useState<UploadProgress | null>(null);

    // Inertia runs one request at a time, so send files one after another.
    const uploadAll = (files: File[]) => {
        const tooBig = files.filter(
            (file) => file.size > maxUploadMegabytes * 1024 * 1024,
        );

        tooBig.forEach((file) =>
            toast.error(
                `${file.name} is larger than ${maxUploadMegabytes} MB.`,
            ),
        );

        const queue = files.filter((file) => !tooBig.includes(file));

        const next = () => {
            const file = queue.shift();

            if (!file) {
                setProgress(null);

                return;
            }

            setProgress({
                name: file.name,
                percent: 0,
                remaining: queue.length,
            });
            uploadAttachment(task, file, {
                onProgress: (percent) =>
                    setProgress((current) =>
                        current ? { ...current, percent } : current,
                    ),
                onFinish: next,
            });
        };

        next();
    };

    return {
        input: (
            <input
                ref={inputRef}
                type="file"
                multiple
                className="hidden"
                onChange={(event) => {
                    uploadAll(Array.from(event.target.files ?? []));
                    event.target.value = '';
                }}
            />
        ),
        openPicker: () => inputRef.current?.click(),
        dragging,
        dragProps: {
            onDragOver: (event) => {
                event.preventDefault();
                setDragging(true);
            },
            onDragLeave: () => setDragging(false),
            onDrop: (event) => {
                event.preventDefault();
                setDragging(false);
                uploadAll(Array.from(event.dataTransfer.files));
            },
        },
        progress,
    };
}

/** The files on a task, one row each. */
export function TaskAttachments({
    task,
    attachments,
    progress,
}: {
    task: Task;
    attachments: Attachment[];
    progress: UploadProgress | null;
}) {
    return (
        <section className="flex flex-col gap-2 border-t px-4 py-3">
            <h3 className="text-sm font-medium">
                Attachments{' '}
                {attachments.length > 0 && (
                    <span className="font-normal text-muted-foreground">
                        {attachments.length}
                    </span>
                )}
            </h3>

            <ul className="flex flex-col">
                {attachments.map((attachment) => (
                    <AttachmentRow
                        key={attachment.id}
                        task={task}
                        attachment={attachment}
                    />
                ))}
            </ul>

            {progress && (
                <div className="flex items-center gap-3 rounded-md border px-3 py-2 text-xs">
                    <Upload className="size-4 shrink-0 animate-pulse text-muted-foreground" />
                    <span className="min-w-0 flex-1 truncate">
                        Uploading {progress.name}
                        {progress.remaining > 0 &&
                            ` (${progress.remaining} more queued)`}
                    </span>
                    <div className="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${progress.percent}%` }}
                        />
                    </div>
                </div>
            )}
        </section>
    );
}

function AttachmentRow({
    task,
    attachment,
}: {
    task: Task;
    attachment: Attachment;
}) {
    const { auth } = usePage().props;
    const { canEdit } = useProject();
    const canDelete = canEdit || attachment.user?.id === auth.user.id;

    return (
        <li className="group -mx-1 flex items-center gap-3 rounded-md px-1 py-1.5 hover:bg-muted/50">
            <Thumbnail attachment={attachment} />
            <div className="min-w-0 flex-1">
                <a
                    href={attachment.download_url}
                    className="block truncate text-sm font-medium hover:underline"
                >
                    {attachment.name}
                </a>
                <p className="truncate text-xs text-muted-foreground">
                    {formatFileSize(attachment.size)} ·{' '}
                    {attachment.user?.name ?? 'Someone'} ·{' '}
                    {formatDateTime(attachment.created_at)}
                </p>
            </div>
            <a
                href={attachment.download_url}
                title="Download"
                className="rounded p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted hover:text-foreground focus-visible:opacity-100"
            >
                <Download className="size-3.5" />
            </a>
            {canDelete && (
                <button
                    type="button"
                    title="Delete"
                    className="rounded p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted hover:text-destructive focus-visible:opacity-100"
                    onClick={() =>
                        confirm(`Delete ${attachment.name}?`) &&
                        deleteAttachment(task, attachment)
                    }
                >
                    <Trash2 className="size-3.5" />
                </button>
            )}
        </li>
    );
}

/** A small preview for images, a file icon for everything else. */
function Thumbnail({ attachment }: { attachment: Attachment }) {
    const [broken, setBroken] = useState(false);
    const extension = attachment.name.includes('.')
        ? attachment.name.split('.').pop()?.slice(0, 4).toUpperCase()
        : null;

    if (attachment.preview_url && !broken) {
        return (
            <a
                href={attachment.preview_url}
                target="_blank"
                rel="noreferrer"
                className="shrink-0"
            >
                <img
                    src={attachment.preview_url}
                    alt=""
                    loading="lazy"
                    className="size-9 rounded border object-cover"
                    onError={() => setBroken(true)}
                />
            </a>
        );
    }

    return (
        <span className="flex size-9 shrink-0 flex-col items-center justify-center rounded border bg-muted/50 text-muted-foreground">
            <FileText className="size-4" />
            {extension && (
                <span className="text-[8px] leading-none font-semibold">
                    {extension}
                </span>
            )}
        </span>
    );
}
