import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, X } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { FIELD_TYPE_ICONS, optionColor } from '@/lib/custom-fields';
import { newKey } from '@/lib/utils';
import { store, update } from '@/routes/teams/fields';
import type {
    CustomField,
    CustomFieldType,
    CustomFieldTypeOption,
    Team,
} from '@/types';

type OptionRow = {
    id: string | null;
    key: string;
    name: string;
    color: string;
};

/** Add a field to the workspace, or edit one (`field` given). */
export function CustomFieldDialog({
    team,
    field,
    fieldTypes,
    open,
    onOpenChange,
}: {
    team: Team;
    field: CustomField | null;
    fieldTypes: CustomFieldTypeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {open && (
                    <FieldForm
                        team={team}
                        field={field}
                        fieldTypes={fieldTypes}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function FieldForm({
    team,
    field,
    fieldTypes,
    onDone,
}: {
    team: Team;
    field: CustomField | null;
    fieldTypes: CustomFieldTypeOption[];
    onDone: () => void;
}) {
    const form = useForm({
        name: field?.name ?? '',
        type: field?.type ?? ('text' as CustomFieldType),
        hidden_from_clients: field?.hidden_from_clients ?? false,
        options: (field?.options ?? []).map((option): OptionRow => ({
            ...option,
            key: option.id,
        })),
    });

    const options = form.data.options;
    const setOptions = (next: OptionRow[]) => form.setData('options', next);

    const changeOption = (index: number, patch: Partial<OptionRow>) =>
        setOptions(
            options.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );

    const moveOption = (index: number, offset: number) => {
        const next = [...options];
        [next[index], next[index + offset]] = [
            next[index + offset],
            next[index],
        ];
        setOptions(next);
    };

    const addOption = () =>
        setOptions([
            ...options,
            {
                id: null,
                key: newKey(),
                name: '',
                color: optionColor(options.length),
            },
        ]);

    const submit = () => {
        const isDropdown = form.data.type === 'dropdown';

        form.transform((data) => ({
            name: data.name,
            hidden_from_clients: data.hidden_from_clients,
            ...(field ? {} : { type: data.type }),
            ...(isDropdown
                ? {
                      options: data.options.map(({ id, name, color }) => ({
                          id,
                          name,
                          color,
                      })),
                  }
                : {}),
        }));

        const visit = { preserveScroll: true, onSuccess: onDone };

        if (field) {
            form.patch(update.url([team.slug, field.id]), visit);
        } else {
            form.post(store.url(team.slug), visit);
        }
    };

    // Option errors come back as options.0.name and so on; show the first.
    const errors = form.errors as Record<string, string | undefined>;
    const optionError =
        errors.options ??
        Object.entries(errors).find(([key]) => key.startsWith('options.'))?.[1];

    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
        >
            <DialogHeader>
                <DialogTitle>
                    {field ? 'Edit custom field' : 'New custom field'}
                </DialogTitle>
                <DialogDescription>
                    {field
                        ? 'Changes apply to every project that uses this field.'
                        : 'Projects in this workspace can add this field to their tasks.'}
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="field-name">Name</Label>
                <Input
                    id="field-name"
                    autoFocus
                    value={form.data.name}
                    placeholder="e.g. Budget, Client, Launch date"
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    required
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="field-type">Type</Label>
                <Select
                    value={form.data.type}
                    disabled={!!field}
                    onValueChange={(value) => {
                        form.setData('type', value as CustomFieldType);

                        if (value === 'dropdown' && options.length === 0) {
                            addOption();
                        }
                    }}
                >
                    <SelectTrigger id="field-type" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {fieldTypes.map((type) => {
                            const Icon = FIELD_TYPE_ICONS[type.value];

                            return (
                                <SelectItem key={type.value} value={type.value}>
                                    <Icon /> {type.label}
                                </SelectItem>
                            );
                        })}
                    </SelectContent>
                </Select>
                {field && (
                    <p className="text-xs text-muted-foreground">
                        A field’s type can’t be changed once it’s created.
                    </p>
                )}
                <InputError message={form.errors.type} />
            </div>

            {form.data.type === 'dropdown' && (
                <div className="grid gap-2">
                    <Label>Options</Label>
                    <ul className="space-y-2">
                        {options.map((row, index) => (
                            <li
                                key={row.key}
                                className="flex items-center gap-2"
                            >
                                <input
                                    type="color"
                                    value={row.color}
                                    aria-label="Option colour"
                                    className="size-8 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                                    onChange={(event) =>
                                        changeOption(index, {
                                            color: event.target.value,
                                        })
                                    }
                                />
                                <Input
                                    value={row.name}
                                    placeholder="Option name"
                                    className="h-8"
                                    autoFocus={row.id === null && index > 0}
                                    onChange={(event) =>
                                        changeOption(index, {
                                            name: event.target.value,
                                        })
                                    }
                                />
                                <div className="flex shrink-0">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        aria-label="Move up"
                                        disabled={index === 0}
                                        onClick={() => moveOption(index, -1)}
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        aria-label="Move down"
                                        disabled={index === options.length - 1}
                                        onClick={() => moveOption(index, 1)}
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        aria-label="Remove option"
                                        disabled={options.length <= 1}
                                        onClick={() =>
                                            setOptions(
                                                options.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addOption}
                        >
                            <Plus /> Add option
                        </Button>
                    </div>
                    {field && (
                        <p className="text-xs text-muted-foreground">
                            Tasks set to an option you remove will be cleared.
                        </p>
                    )}
                    <InputError message={optionError} />
                </div>
            )}

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
                        Clients won’t see this field or its values on tasks.
                    </span>
                </span>
            </label>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {field ? 'Save field' : 'Add field'}
                </Button>
            </DialogFooter>
        </form>
    );
}
