import { Link, router, usePage } from '@inertiajs/react';
import { EyeOff } from 'lucide-react';
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
import { FIELD_TYPE_ICONS } from '@/lib/custom-fields';
import { update } from '@/routes/projects/fields';
import { edit as editTeam } from '@/routes/teams';
import type { CustomField, Project } from '@/types';

/** Choose which of the workspace's custom fields the project uses. */
export function ProjectFieldsDialog({
    project,
    enabled,
    workspaceFields,
    canManageFields,
    open,
    onOpenChange,
}: {
    project: Project;
    enabled: CustomField[];
    workspaceFields: CustomField[];
    canManageFields: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                {open && (
                    <FieldsForm
                        project={project}
                        enabled={enabled}
                        workspaceFields={workspaceFields}
                        canManageFields={canManageFields}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function FieldsForm({
    project,
    enabled,
    workspaceFields,
    canManageFields,
    onDone,
}: {
    project: Project;
    enabled: CustomField[];
    workspaceFields: CustomField[];
    canManageFields: boolean;
    onDone: () => void;
}) {
    const { currentTeam } = usePage().props;
    const [selected, setSelected] = useState(
        () => new Set(enabled.map((field) => field.id)),
    );
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const toggle = (id: number, on: boolean) =>
        setSelected((current) => {
            const next = new Set(current);

            if (on) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });

    const save = () => {
        router.put(
            update.url({ project: project.id }),
            {
                custom_field_ids: workspaceFields
                    .filter((field) => selected.has(field.id))
                    .map((field) => field.id),
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

    const manageLink =
        canManageFields && currentTeam ? (
            <Link
                href={editTeam(currentTeam.slug)}
                className="text-sm font-medium text-primary hover:underline"
            >
                Manage workspace fields
            </Link>
        ) : null;

    return (
        <div className="space-y-6">
            <DialogHeader>
                <DialogTitle>Custom fields</DialogTitle>
                <DialogDescription>
                    Pick the workspace fields this project’s tasks use. Turning
                    a field off hides it here but keeps the values, so turning
                    it back on brings them back.
                </DialogDescription>
            </DialogHeader>

            {workspaceFields.length === 0 ? (
                <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                    This workspace has no custom fields yet.
                    {canManageFields
                        ? ' Add some in the workspace settings.'
                        : ' Ask a workspace admin to add some.'}
                </p>
            ) : (
                <ul className="max-h-80 space-y-1 overflow-y-auto">
                    {workspaceFields.map((field) => {
                        const Icon = FIELD_TYPE_ICONS[field.type];

                        return (
                            <li key={field.id}>
                                <label className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted">
                                    <Checkbox
                                        checked={selected.has(field.id)}
                                        onCheckedChange={(checked) =>
                                            toggle(field.id, checked === true)
                                        }
                                    />
                                    <Icon className="size-4 text-muted-foreground" />
                                    <span className="flex-1 truncate">
                                        {field.name}
                                    </span>
                                    {field.hidden_from_clients && (
                                        <EyeOff
                                            className="size-3.5 text-muted-foreground"
                                            aria-label="Hidden from clients"
                                        />
                                    )}
                                </label>
                            </li>
                        );
                    })}
                </ul>
            )}

            <InputError message={error ?? undefined} />

            <DialogFooter className="items-center gap-2 sm:justify-between">
                {manageLink ?? <span />}
                {workspaceFields.length > 0 && (
                    <Button type="button" disabled={processing} onClick={save}>
                        Save
                    </Button>
                )}
            </DialogFooter>
        </div>
    );
}
