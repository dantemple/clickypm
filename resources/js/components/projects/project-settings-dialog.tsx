import { router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { ColorSwatches } from '@/components/projects/color-swatches';
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
import { Label } from '@/components/ui/label';
import { destroy, update } from '@/routes/projects';
import type { Project } from '@/types';

export function ProjectSettingsDialog({
    project,
    open,
    onOpenChange,
}: {
    project: Project;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                {open && (
                    <SettingsForm
                        project={project}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function SettingsForm({
    project,
    onDone,
}: {
    project: Project;
    onDone: () => void;
}) {
    const form = useForm({
        name: project.name,
        color: project.color,
        description: project.description ?? '',
        hidden_from_clients: project.hidden_from_clients,
    });

    const deleteProject = () => {
        if (
            confirm(
                `Delete “${project.name}” and all of its tasks? This cannot be undone.`,
            )
        ) {
            router.delete(destroy.url({ project: project.id }));
        }
    };

    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(update.url({ project: project.id }), {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>Project settings</DialogTitle>
                <DialogDescription>
                    Rename the project or change its colour.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="settings-name">Name</Label>
                <Input
                    id="settings-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    required
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label>Colour</Label>
                <ColorSwatches
                    value={form.data.color}
                    onChange={(color) => form.setData('color', color)}
                />
            </div>

            <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                <Checkbox
                    className="mt-0.5"
                    checked={form.data.hidden_from_clients}
                    onCheckedChange={(checked) =>
                        form.setData('hidden_from_clients', checked === true)
                    }
                />
                <span>
                    <span className="font-medium">Hide from clients</span>
                    <span className="block text-muted-foreground">
                        Clients in this workspace won’t see this project or its
                        tasks.
                    </span>
                </span>
            </label>

            <div className="grid gap-2">
                <Label htmlFor="settings-description">Description</Label>
                <textarea
                    id="settings-description"
                    rows={3}
                    value={form.data.description}
                    className="rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
                <InputError message={form.errors.description} />
            </div>

            <DialogFooter className="gap-2 sm:justify-between">
                {project.canDelete ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="text-destructive hover:text-destructive"
                        onClick={deleteProject}
                    >
                        Delete project
                    </Button>
                ) : (
                    <span />
                )}
                <Button type="submit" disabled={form.processing}>
                    Save
                </Button>
            </DialogFooter>
        </form>
    );
}
