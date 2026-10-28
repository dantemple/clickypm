import { router } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { optionColor } from '@/lib/custom-fields';
import { MAX_TAGS_PER_PROJECT } from '@/lib/tasks';
import { newKey } from '@/lib/utils';
import { update } from '@/routes/projects/tags';
import type { Project, Tag } from '@/types';

type Row = Pick<Tag, 'name' | 'color'> & {
    id: number | null;
    key: string;
    tasks_count: number;
};

export function TagsDialog({
    project,
    tags,
    open,
    onOpenChange,
}: {
    project: Project;
    tags: Tag[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {open && (
                    <TagsForm
                        project={project}
                        tags={tags}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function TagsForm({
    project,
    tags,
    onDone,
}: {
    project: Project;
    tags: Tag[];
    onDone: () => void;
}) {
    const [rows, setRows] = useState<Row[]>(() =>
        tags.map((tag) => ({
            ...tag,
            key: String(tag.id),
            tasks_count: tag.tasks_count ?? 0,
        })),
    );
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const change = (index: number, patch: Partial<Row>) =>
        setRows((current) =>
            current.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );

    const add = () =>
        setRows((current) => [
            ...current,
            {
                id: null,
                key: newKey(),
                name: '',
                color: optionColor(current.length),
                tasks_count: 0,
            },
        ]);

    const remove = (index: number) => {
        const row = rows[index];

        if (
            row.tasks_count > 0 &&
            !confirm(
                `Remove “${row.name}” from ${row.tasks_count} ${row.tasks_count === 1 ? 'task' : 'tasks'}?`,
            )
        ) {
            return;
        }

        setRows((current) => current.filter((_, i) => i !== index));
    };

    const save = () => {
        router.put(
            update.url({ project: project.id }),
            {
                tags: rows.map(({ id, name, color }) => ({ id, name, color })),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onDone,
                onError: (errors) =>
                    setError(Object.values(errors)[0] ?? 'Could not save.'),
            },
        );
    };

    return (
        <div className="space-y-6">
            <DialogHeader>
                <DialogTitle>Tags</DialogTitle>
                <DialogDescription>
                    Labels any task in this project can carry. Removing a tag
                    takes it off every task that has it.
                </DialogDescription>
            </DialogHeader>

            {rows.length === 0 ? (
                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    No tags yet. Add one here, or type a new tag straight onto a
                    task.
                </p>
            ) : (
                <ul className="space-y-2">
                    {rows.map((row, index) => (
                        <li key={row.key} className="flex items-center gap-2">
                            <input
                                type="color"
                                value={row.color}
                                aria-label="Tag colour"
                                className="size-8 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                                onChange={(event) =>
                                    change(index, { color: event.target.value })
                                }
                            />
                            <Input
                                value={row.name}
                                placeholder="Tag name"
                                maxLength={30}
                                className="h-8"
                                autoFocus={row.id === null}
                                onChange={(event) =>
                                    change(index, { name: event.target.value })
                                }
                            />
                            <span className="w-16 shrink-0 text-right text-xs text-muted-foreground">
                                {row.tasks_count === 0
                                    ? 'Unused'
                                    : `${row.tasks_count} ${row.tasks_count === 1 ? 'task' : 'tasks'}`}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 shrink-0"
                                aria-label="Remove tag"
                                onClick={() => remove(index)}
                            >
                                <X />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={rows.length >= MAX_TAGS_PER_PROJECT}
                onClick={add}
            >
                <Plus /> Add tag
            </Button>

            <InputError message={error ?? undefined} />

            <DialogFooter>
                <Button type="button" disabled={processing} onClick={save}>
                    Save tags
                </Button>
            </DialogFooter>
        </div>
    );
}
