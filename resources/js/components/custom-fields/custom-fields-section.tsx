import { router } from '@inertiajs/react';
import { EyeOff, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CustomFieldDialog } from '@/components/custom-fields/custom-field-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { FIELD_TYPE_ICONS } from '@/lib/custom-fields';
import { destroy } from '@/routes/teams/fields';
import type { CustomField, CustomFieldTypeOption, Team } from '@/types';

export type WorkspaceField = CustomField & { projects_count: number };

/** The workspace's custom fields, on its settings page. */
export function CustomFieldsSection({
    team,
    fields,
    fieldTypes,
    canManage,
}: {
    team: Team;
    fields: WorkspaceField[];
    fieldTypes: CustomFieldTypeOption[];
    canManage: boolean;
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<CustomField | null>(null);

    const typeLabel = (field: CustomField) =>
        fieldTypes.find((type) => type.value === field.type)?.label ??
        field.type;

    const openDialog = (field: CustomField | null) => {
        setEditing(field);
        setDialogOpen(true);
    };

    const remove = (field: WorkspaceField) => {
        const usage =
            field.projects_count > 0
                ? ` It will be removed from ${field.projects_count} ${field.projects_count === 1 ? 'project' : 'projects'}, along with every task’s value.`
                : '';

        if (confirm(`Delete the “${field.name}” field?${usage}`)) {
            router.delete(destroy.url([team.slug, field.id]), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Custom fields"
                    description="Fields projects in this workspace can add to their tasks"
                />

                {canManage && (
                    <Button
                        data-test="add-custom-field-button"
                        onClick={() => openDialog(null)}
                    >
                        <Plus /> Add field
                    </Button>
                )}
            </div>

            {fields.length === 0 ? (
                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    No custom fields yet.
                    {canManage &&
                        ' Add one to track things like budgets, clients or launch dates across your projects.'}
                </p>
            ) : (
                <ul className="space-y-3">
                    {fields.map((field) => {
                        const Icon = FIELD_TYPE_ICONS[field.type];

                        return (
                            <li
                                key={field.id}
                                data-test="custom-field-row"
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="flex min-w-0 items-center gap-4">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                        <Icon className="size-5 text-muted-foreground" />
                                    </div>
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 font-medium">
                                            <span className="truncate">
                                                {field.name}
                                            </span>
                                            {field.hidden_from_clients && (
                                                <span className="flex shrink-0 items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                                                    <EyeOff className="size-3" />{' '}
                                                    Internal
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {typeLabel(field)}
                                            {field.type === 'dropdown' &&
                                                ` · ${field.options.length} ${field.options.length === 1 ? 'option' : 'options'}`}
                                            {' · '}
                                            {field.projects_count === 0
                                                ? 'Not used yet'
                                                : `Used in ${field.projects_count} ${field.projects_count === 1 ? 'project' : 'projects'}`}
                                        </div>
                                    </div>
                                </div>

                                {canManage && (
                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            aria-label={`Edit ${field.name}`}
                                            onClick={() => openDialog(field)}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            aria-label={`Delete ${field.name}`}
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => remove(field)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {canManage && (
                <CustomFieldDialog
                    team={team}
                    field={editing}
                    fieldTypes={fieldTypes}
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                />
            )}
        </div>
    );
}
