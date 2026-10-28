import { ArrowDown, ArrowUp, Maximize2 } from 'lucide-react';
import { useRef } from 'react';
import { CustomFieldInput } from '@/components/custom-fields/custom-field-input';
import { useProject } from '@/components/projects/project-context';
import { AddTaskRow } from '@/components/tasks/add-task-row';
import { TagList } from '@/components/tasks/tag-badge';
import { TaskBadges } from '@/components/tasks/task-badges';
import {
    AssigneePicker,
    DueDatePicker,
    PriorityPicker,
    StatusPicker,
    StatusToggle,
} from '@/components/tasks/pickers';
import { fieldColumn, fieldForColumn } from '@/lib/custom-fields';
import { formatDateTime, updateTask } from '@/lib/tasks';
import { useSyncedDraft } from '@/hooks/use-synced-draft';
import { cn } from '@/lib/utils';
import { sortTasks } from '@/lib/views';
import type {
    CustomField,
    FieldColumn,
    SortColumn,
    Task,
    TaskColumn,
    ViewSettings,
} from '@/types';

type Column = TaskColumn | FieldColumn;

export const TABLE_COLUMNS: Record<TaskColumn, string> = {
    status: 'Status',
    assignee: 'Assignee',
    priority: 'Priority',
    tags: 'Tags',
    due_date: 'Due date',
    created_at: 'Created',
};

/** Every column a table can show: the built-in ones, then the project's fields. */
export function tableColumns(fields: CustomField[]): Column[] {
    return [
        ...(Object.keys(TABLE_COLUMNS) as TaskColumn[]),
        ...fields.map(fieldColumn),
    ];
}

export function columnLabel(column: Column, fields: CustomField[]): string {
    return (
        fieldForColumn(column, fields)?.name ??
        TABLE_COLUMNS[column as TaskColumn]
    );
}

export function TableView({
    tasks,
    settings,
    onSettingsChange,
}: {
    tasks: Task[];
    settings: ViewSettings;
    onSettingsChange: (settings: Partial<ViewSettings>) => void;
}) {
    const { statuses, members, customFields } = useProject();
    const sort = settings.sort ?? { column: 'position', direction: 'asc' };
    const columns = tableColumns(customFields).filter((column) =>
        settings.columns?.includes(column),
    );
    const sorted = sortTasks(
        tasks,
        sort.column,
        sort.direction,
        statuses,
        members,
        customFields,
    );

    // Click once for ascending, again for descending, a third time to reset.
    const cycleSort = (column: SortColumn) => {
        const next =
            sort.column !== column
                ? { column, direction: 'asc' as const }
                : sort.direction === 'asc'
                  ? { column, direction: 'desc' as const }
                  : { column: 'position' as const, direction: 'asc' as const };

        onSettingsChange({ sort: next });
    };

    const header = (column: SortColumn, label: string, className?: string) => (
        <th
            key={column}
            className={cn(
                'border-r border-b px-3 py-2 text-left text-xs font-medium whitespace-nowrap text-muted-foreground last:border-r-0',
                className,
            )}
        >
            <button
                type="button"
                className="flex items-center gap-1 hover:text-foreground"
                onClick={() => cycleSort(column)}
            >
                {label}
                {sort.column === column &&
                    (sort.direction === 'asc' ? (
                        <ArrowUp className="size-3" />
                    ) : (
                        <ArrowDown className="size-3" />
                    ))}
            </button>
        </th>
    );

    return (
        <div className="p-4">
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full border-collapse text-sm">
                    <thead className="bg-muted/40">
                        <tr>
                            {header('title', 'Name', 'min-w-72')}
                            {columns.map((column) =>
                                header(
                                    column,
                                    columnLabel(column, customFields),
                                    'w-36',
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((task) => (
                            <TableRow
                                key={task.id}
                                task={task}
                                columns={columns}
                            />
                        ))}
                        {sorted.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columns.length + 1}
                                    className="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No tasks match this view.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
                <AddTaskRow className="border-t" />
            </div>
        </div>
    );
}

function TableRow({ task, columns }: { task: Task; columns: Column[] }) {
    const { openTask, customFields } = useProject();

    const cell = (column: Column) => {
        const field = fieldForColumn(column, customFields);

        if (field) {
            return (
                <CustomFieldInput task={task} field={field} variant="cell" />
            );
        }

        switch (column) {
            case 'status':
                return <StatusPicker task={task} />;
            case 'assignee':
                return <AssigneePicker task={task} showName />;
            case 'priority':
                return <PriorityPicker task={task} showLabel />;
            case 'tags':
                return <TagList tags={task.tags} />;
            case 'due_date':
                return <DueDatePicker task={task} />;
            case 'created_at':
                return (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {formatDateTime(task.created_at)}
                    </span>
                );
            default:
                return null;
        }
    };

    return (
        <tr className="group hover:bg-muted/40">
            <td className="border-r border-b px-3 py-1">
                <div className="flex items-center gap-2">
                    <StatusToggle task={task} />
                    <TitleInput task={task} />
                    <TaskBadges task={task} />
                    <button
                        type="button"
                        title="Open task"
                        className="rounded p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted hover:text-foreground focus-visible:opacity-100"
                        onClick={() => openTask(task)}
                    >
                        <Maximize2 className="size-3.5" />
                    </button>
                </div>
            </td>
            {columns.map((column) => (
                <td
                    key={column}
                    className="border-r border-b px-3 py-1 last:border-r-0"
                >
                    {cell(column)}
                </td>
            ))}
        </tr>
    );
}

/** Spreadsheet-style title cell: edit in place, commit on Enter or blur. */
function TitleInput({ task }: { task: Task }) {
    const { canEdit, openTask } = useProject();
    // Follows changes made elsewhere (the task sheet, other people) unless
    // you're typing in it.
    const {
        draft: title,
        setDraft: setTitle,
        focusProps,
    } = useSyncedDraft(task.title);
    const cancelled = useRef(false);

    const commit = () => {
        const trimmed = title.trim();

        if (cancelled.current) {
            cancelled.current = false;
            setTitle(task.title);
        } else if (trimmed === '') {
            setTitle(task.title);
        } else if (trimmed !== task.title) {
            updateTask(task, { title: trimmed });
        }
    };

    if (!canEdit) {
        return (
            <button
                type="button"
                className={cn(
                    'min-w-0 flex-1 truncate px-1 text-left hover:underline',
                    task.completed_at && 'text-muted-foreground line-through',
                )}
                onClick={() => openTask(task)}
            >
                {task.title}
            </button>
        );
    }

    return (
        <input
            value={title}
            aria-label="Task name"
            className={cn(
                'h-7 min-w-0 flex-1 rounded bg-transparent px-1 outline-none focus:bg-background focus:ring-2 focus:ring-ring',
                task.completed_at && 'text-muted-foreground line-through',
            )}
            onChange={(event) => setTitle(event.target.value)}
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
}
