import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/projects/statuses';
import type { Project, Status } from '@/types';

type Row = Omit<Status, 'id'> & { id: number | null; key: string };

export function StatusesDialog({
    project,
    statuses,
    open,
    onOpenChange,
}: {
    project: Project;
    statuses: Status[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {open && (
                    <StatusesForm
                        project={project}
                        statuses={statuses}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function StatusesForm({
    project,
    statuses,
    onDone,
}: {
    project: Project;
    statuses: Status[];
    onDone: () => void;
}) {
    const [rows, setRows] = useState<Row[]>(() =>
        statuses.map((status) => ({ ...status, key: String(status.id) })),
    );
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const change = (index: number, patch: Partial<Row>) =>
        setRows((current) =>
            current.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );

    const move = (index: number, offset: number) =>
        setRows((current) => {
            const next = [...current];
            [next[index], next[index + offset]] = [
                next[index + offset],
                next[index],
            ];

            return next;
        });

    const add = () =>
        setRows((current) => [
            ...current.filter((row) => !row.is_done),
            {
                id: null,
                key: crypto.randomUUID(),
                name: '',
                color: '#8b5cf6',
                is_done: false,
            },
            ...current.filter((row) => row.is_done),
        ]);

    const save = () => {
        router.put(
            update.url({ project: project.id }),
            {
                statuses: rows.map(({ id, name, color, is_done }) => ({
                    id,
                    name,
                    color,
                    is_done,
                })),
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
                <DialogTitle>Statuses</DialogTitle>
                <DialogDescription>
                    Tasks move through these in order. Tasks in a status marked
                    “Done” count as complete. Removing a status moves its tasks
                    to the first status of the same kind.
                </DialogDescription>
            </DialogHeader>

            <ul className="space-y-2">
                {rows.map((row, index) => (
                    <li key={row.key} className="flex items-center gap-2">
                        <input
                            type="color"
                            value={row.color}
                            aria-label="Status colour"
                            className="size-8 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                            onChange={(event) =>
                                change(index, { color: event.target.value })
                            }
                        />
                        <Input
                            value={row.name}
                            placeholder="Status name"
                            className="h-8"
                            autoFocus={row.id === null}
                            onChange={(event) =>
                                change(index, { name: event.target.value })
                            }
                        />
                        <label className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                            <Checkbox
                                checked={row.is_done}
                                onCheckedChange={(checked) =>
                                    change(index, { is_done: checked === true })
                                }
                            />
                            Done
                        </label>
                        <div className="flex shrink-0">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label="Move up"
                                disabled={index === 0}
                                onClick={() => move(index, -1)}
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label="Move down"
                                disabled={index === rows.length - 1}
                                onClick={() => move(index, 1)}
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label="Remove status"
                                disabled={rows.length <= 1}
                                onClick={() =>
                                    setRows((current) =>
                                        current.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <X />
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>

            <Button type="button" variant="outline" size="sm" onClick={add}>
                <Plus /> Add status
            </Button>

            <InputError message={error ?? undefined} />

            <DialogFooter>
                <Button type="button" disabled={processing} onClick={save}>
                    Save statuses
                </Button>
            </DialogFooter>
        </div>
    );
}
