import type { LucideIcon } from 'lucide-react';
import {
    CalendarDays,
    CircleChevronDown,
    Hash,
    Link,
    SquareCheck,
    Type,
} from 'lucide-react';
import { PROJECT_COLORS, formatDue, updateTask } from '@/lib/tasks';
import type {
    CustomField,
    CustomFieldType,
    CustomFieldValue,
    FieldColumn,
    Task,
} from '@/types';

export const FIELD_TYPE_ICONS: Record<CustomFieldType, LucideIcon> = {
    text: Type,
    number: Hash,
    date: CalendarDays,
    dropdown: CircleChevronDown,
    checkbox: SquareCheck,
    url: Link,
};

/** A colour for a new dropdown option, cycling through the palette. */
export function optionColor(index: number): string {
    return PROJECT_COLORS[index % PROJECT_COLORS.length];
}

export function fieldColumn(field: CustomField): FieldColumn {
    return `field_${field.id}`;
}

/** The field a table column shows, if it's a custom field column. */
export function fieldForColumn(
    column: string,
    fields: CustomField[],
): CustomField | undefined {
    return column.startsWith('field_')
        ? fields.find((field) => fieldColumn(field) === column)
        : undefined;
}

export function fieldValue(
    task: Task,
    field: CustomField,
): CustomFieldValue | null {
    return task.custom_fields[field.id] ?? null;
}

/** Set (or, with null, clear) one of the task's custom field values. */
export function setFieldValue(
    task: Task,
    field: CustomField,
    value: CustomFieldValue | null,
): void {
    updateTask(task, { custom_fields: { [field.id]: value } });
}

/** A short, human version of a value, for read-only places. */
export function formatFieldValue(
    field: CustomField,
    value: CustomFieldValue | null,
): string | null {
    if (value === null) {
        return null;
    }

    switch (field.type) {
        case 'dropdown':
            return (
                field.options.find((option) => option.id === value)?.name ??
                null
            );
        case 'date':
            return formatDue(String(value));
        case 'checkbox':
            return value ? 'Yes' : null;
        case 'number':
            return Number(value).toLocaleString('en-GB', {
                maximumFractionDigits: 10,
            });
        default:
            return String(value);
    }
}

/** What a task sorts by in a custom field column; null sorts last. */
export function fieldSortValue(
    field: CustomField,
    value: CustomFieldValue | null,
): string | number | null {
    if (value === null) {
        return null;
    }

    switch (field.type) {
        case 'number':
            return Number(value);
        case 'dropdown': {
            const index = field.options.findIndex(
                (option) => option.id === value,
            );

            return index === -1 ? null : index;
        }
        case 'checkbox':
            return value ? 0 : null;
        case 'text':
        case 'url':
            return String(value).toLowerCase();
        default:
            return String(value);
    }
}
