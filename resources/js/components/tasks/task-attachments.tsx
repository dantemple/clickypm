import { usePage } from '@inertiajs/react';
import { Download, FileText, Paperclip, Trash2, Upload } from 'lucide-react';
import type { DragEvent } from 'react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { useProject } from '@/components/projects/project-context';
import { Button } from '@/components/ui/button';
import {
    deleteAttachment,
    formatDateTime,
    formatFileSize,
    uploadAttachment,
} from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Attachment, Task } from '@/types';

export function TaskAttachments({
    task,
    attachments,
}: {
    task: Task;
    attachments: Attachment[];
}) {
    const { maxUploadMegabytes } = usePage<{ maxUploadMegabytes: number }>()
        .props;
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [upload, setUpload] = useState<{
        name: string;
        percent: number;
        remaining: number;
    } | null>(null);

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
                setUpload(null);

                return;
            }

            setUpload({ name: file.name, percent: 0, remaining: queue.length });
            uploadAttachment(task, file, {
                onProgress: (percent) =>
                    setUpload((current) =>
                        current ? { ...current, percent } : current,
                    ),
                onFinish: next,
            });
        };

        next();
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        setDragging(false);
        uploadAll(Array.from(event.dataTransfer.files));
    };

    return (
        <div
            className={cn(
                'flex flex-col gap-2 border-t p-4 transition-colors',
                dragging && 'bg-primary/5 ring-2 ring-primary/40 ring-inset',
            )}
            onDragOver={(event) => {
                event.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={onDrop}
        >
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium">
                    Attachments{' '}
                    {attachments.length > 0 && (
                        <span className="font-normal text-muted-foreground">
                            {attachments.length}
                        </span>
                    )}
                </h3>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={!!upload}
                    onClick={() => input.current?.click()}
                >
                    <Paperclip /> Attach files
                </Button>
                <input
                    ref={input}
                    type="file"
                    multiple
                    className="hidden"
                    onChange={(event) => {
                        uploadAll(Array.from(event.target.files ?? []));
                        event.target.value = '';
                    }}
                />
            </div>

            {upload && (
                <div className="flex items-center gap-3 rounded-md border px-3 py-2 text-xs">
                    <Upload className="size-4 shrink-0 animate-pulse text-muted-foreground" />
                    <span className="min-w-0 flex-1 truncate">
                        Uploading {upload.name}
                        {upload.remaining > 0 &&
                            ` (${upload.remaining} more queued)`}
                    </span>
                    <div className="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${upload.percent}%` }}
                        />
                    </div>
                </div>
            )}

            {attachments.length === 0 && !upload ? (
                <button
                    type="button"
                    className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground hover:bg-muted/40"
                    onClick={() => input.current?.click()}
                >
                    Drop files here or click to attach (up to{' '}
                    {maxUploadMegabytes} MB each)
                </button>
            ) : (
                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {attachments.map((attachment) => (
                        <AttachmentCard
                            key={attachment.id}
                            task={task}
                            attachment={attachment}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}

function AttachmentCard({
    task,
    attachment,
}: {
    task: Task;
    attachment: Attachment;
}) {
    const { auth } = usePage().props;
    const { canEdit } = useProject();
    const canDelete = canEdit || attachment.user?.id === auth.user.id;
    const extension = attachment.name.includes('.')
        ? attachment.name.split('.').pop()?.slice(0, 4).toUpperCase()
        : null;

    return (
        <li
            className="group relative overflow-hidden rounded-md border"
            title={`${attachment.name}\n${formatFileSize(attachment.size)} · ${attachment.user?.name ?? 'Someone'} · ${formatDateTime(attachment.created_at)}`}
        >
            <a
                href={attachment.preview_url ?? attachment.download_url}
                target={attachment.preview_url ? '_blank' : undefined}
                rel="noreferrer"
                className="flex aspect-[4/3] items-center justify-center bg-muted/50"
            >
                {attachment.preview_url ? (
                    <img
                        src={attachment.preview_url}
                        alt={attachment.name}
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <span className="flex flex-col items-center gap-1 text-muted-foreground">
                        <FileText className="size-7" />
                        {extension && (
                            <span className="text-[10px] font-semibold">
                                {extension}
                            </span>
                        )}
                    </span>
                )}
            </a>
            <div className="flex items-center gap-1 border-t px-2 py-1.5">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-xs font-medium">
                        {attachment.name}
                    </p>
                    <p className="text-[10px] text-muted-foreground">
                        {formatFileSize(attachment.size)}
                    </p>
                </div>
                <a
                    href={attachment.download_url}
                    title="Download"
                    className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <Download className="size-3.5" />
                </a>
                {canDelete && (
                    <button
                        type="button"
                        title="Delete"
                        className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive"
                        onClick={() =>
                            confirm(`Delete ${attachment.name}?`) &&
                            deleteAttachment(task, attachment)
                        }
                    >
                        <Trash2 className="size-3.5" />
                    </button>
                )}
            </div>
        </li>
    );
}
