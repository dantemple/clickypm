import { Check, ExternalLink, X } from 'lucide-react';
import { useRef } from 'react';
import { useProject } from '@/components/projects/project-context';
import { DueDateButton } from '@/components/tasks/pickers';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useSyncedDraft } from '@/hooks/use-synced-draft';
import {
    fieldValue,
    formatFieldValue,
    setFieldValue,
} from '@/lib/custom-fields';
import { cn } from '@/lib/utils';
import type { CustomField, CustomFieldOption, Task } from '@/types';

type Variant = 'sheet' | 'cell';

/**
 * View or edit one of a task's custom field values: a full-size control in
 * the task panel, a compact one in a table cell.
 */
export function CustomFieldInput({
    task,
    field,
    variant = 'sheet',
}: {
    task: Task;
    field: CustomField;
    variant?: Variant;
}) {
    const { canEdit } = useProject();
    const value = fieldValue(task, field);

    if (!canEdit) {
        return <ReadOnlyValue field={field} task={task} variant={variant} />;
    }

    switch (field.type) {
        case 'dropdown':
            return (
                <DropdownValue task={task} field={field} variant={variant} />
            );
        case 'checkbox':
            return (
                <Checkbox
                    aria-label={field.name}
                    checked={value === true}
                    onClick={(event) => event.stopPropagation()}
                    onCheckedChange={(checked) =>
                        setFieldValue(task, field, checked === true)
                    }
                />
            );
        case 'date':
            return variant === 'cell' ? (
                <DueDateButton
                    date={value === null ? null : String(value)}
                    overdue={false}
                    label={field.name}
                    onChange={(date) => setFieldValue(task, field, date)}
                />
            ) : (
                <span className="flex items-center gap-2">
                    <Input
                        type="date"
                        aria-label={field.name}
                        className="h-8 w-44"
                        value={value === null ? '' : String(value)}
                        onChange={(event) =>
                            setFieldValue(
                                task,
                                field,
                                event.target.value || null,
                            )
                        }
                    />
                    {value !== null && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setFieldValue(task, field, null)}
                        >
                            Clear
                        </Button>
                    )}
                </span>
            );
        default:
            return <TextValue task={task} field={field} variant={variant} />;
    }
}

function ReadOnlyValue({
    task,
    field,
    variant,
}: {
    task: Task;
    field: CustomField;
    variant: Variant;
}) {
    const value = fieldValue(task, field);
    const option = field.options.find((option) => option.id === value);
    const formatted = formatFieldValue(field, value);

    if (field.type === 'dropdown' && option) {
        return <OptionChip option={option} />;
    }

    if (field.type === 'checkbox') {
        return value ? (
            <Check className="size-4" aria-label="Yes" />
        ) : variant === 'sheet' ? (
            <span className="text-muted-foreground">No</span>
        ) : null;
    }

    if (field.type === 'url' && formatted) {
        return (
            <a
                href={formatted}
                target="_blank"
                rel="noopener noreferrer"
                className="truncate text-primary hover:underline"
                onClick={(event) => event.stopPropagation()}
            >
                {formatted}
            </a>
        );
    }

    if (formatted === null) {
        return variant === 'sheet' ? (
            <span className="text-muted-foreground">None</span>
        ) : null;
    }

    return <span className="truncate">{formatted}</span>;
}

export function OptionChip({
    option,
    className,
}: {
    option: Pick<CustomFieldOption, 'name' | 'color'>;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-6 max-w-full items-center truncate rounded px-2 text-xs font-medium whitespace-nowrap text-white',
                className,
            )}
            style={{ backgroundColor: option.color }}
        >
            {option.name}
        </span>
    );
}

function DropdownValue({
    task,
    field,
    variant,
}: {
    task: Task;
    field: CustomField;
    variant: Variant;
}) {
    const value = fieldValue(task, field);
    const current = field.options.find((option) => option.id === value);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="max-w-full rounded text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onClick={(event) => event.stopPropagation()}
            >
                {current ? (
                    <OptionChip option={current} />
                ) : (
                    <span className="text-muted-foreground/60">
                        {variant === 'sheet' ? 'Select…' : '—'}
                    </span>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-48"
                onClick={(event) => event.stopPropagation()}
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    {field.name}
                </DropdownMenuLabel>
                {field.options.map((option) => (
                    <DropdownMenuItem
                        key={option.id}
                        onSelect={() =>
                            option.id !== value &&
                            setFieldValue(task, field, option.id)
                        }
                    >
                        <span
                            className="size-2.5 rounded-full"
                            style={{ backgroundColor: option.color }}
                        />
                        <span className="truncate">{option.name}</span>
                        {option.id === value && <Check className="ml-auto" />}
                    </DropdownMenuItem>
                ))}
                {current && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => setFieldValue(task, field, null)}
                        >
                            <X /> Clear
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Text, number and link fields: edit in place, save on Enter or blur. */
function TextValue({
    task,
    field,
    variant,
}: {
    task: Task;
    field: CustomField;
    variant: Variant;
}) {
    const value = fieldValue(task, field);
    const saved = value === null ? '' : String(value);
    // Follows other people's edits, except while you're typing in it.
    const { draft, setDraft, focusProps } = useSyncedDraft(saved);
    const cancelled = useRef(false);

    const commit = () => {
        const trimmed = draft.trim();

        if (cancelled.current) {
            cancelled.current = false;
            setDraft(saved);
        } else if (trimmed !== saved) {
            setFieldValue(task, field, trimmed === '' ? null : trimmed);
        }
    };

    const input = (
        <input
            type={
                field.type === 'number'
                    ? 'number'
                    : field.type === 'url'
                      ? 'url'
                      : 'text'
            }
            step={field.type === 'number' ? 'any' : undefined}
            value={draft}
            aria-label={field.name}
            placeholder={
                variant === 'sheet'
                    ? field.type === 'url'
                        ? 'https://'
                        : 'Empty'
                    : undefined
            }
            className={cn(
                'min-w-0 flex-1 bg-transparent outline-none placeholder:text-muted-foreground/60',
                variant === 'sheet'
                    ? 'h-8 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring'
                    : 'h-7 w-full min-w-28 rounded px-1 focus:bg-background focus:ring-2 focus:ring-ring',
            )}
            onClick={(event) => event.stopPropagation()}
            onChange={(event) => setDraft(event.target.value)}
            onFocus={focusProps.onFocus}
            onBlur={() => {
                focusProps.onBlur();
                commit();
            }}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                }

                if (event.key === 'Escape') {
                    cancelled.current = true;
                    event.currentTarget.blur();
                }
            }}
        />
    );

    if (field.type !== 'url' || value === null) {
        return variant === 'sheet' ? (
            <span className="flex">{input}</span>
        ) : (
            input
        );
    }

    return (
        <span className="flex items-center gap-1">
            {input}
            <a
                href={String(value)}
                target="_blank"
                rel="noopener noreferrer"
                title="Open link"
                className="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                onClick={(event) => event.stopPropagation()}
            >
                <ExternalLink className="size-3.5" />
            </a>
        </span>
    );
}
