export type ViewType = 'list' | 'board' | 'table' | 'gantt';

export type GanttZoom = 'day' | 'week' | 'month' | 'year';

/** A finish-to-start link: the successor waits for the predecessor. */
export type TaskDependency = {
    id: number;
    predecessor_id: number;
    successor_id: number;
    type: string;
    lag_days: number;
};

export type GroupBy = 'status' | 'priority' | 'assignee';

export type TaskColumn =
    | 'status'
    | 'assignee'
    | 'priority'
    | 'due_date'
    | 'created_at';

/** A table column showing one of the project's custom fields. */
export type FieldColumn = `field_${number}`;

export type SortColumn = 'position' | 'title' | TaskColumn | FieldColumn;

export type SortDirection = 'asc' | 'desc';

export type ViewSettings = {
    show_closed: boolean;
    group_by?: GroupBy;
    sort?: { column: SortColumn; direction: SortDirection };
    columns?: (TaskColumn | FieldColumn)[];
    zoom?: GanttZoom;
};

export type ProjectView = {
    id: number;
    name: string;
    type: ViewType;
    settings: ViewSettings;
};

export type ProjectSummary = {
    id: number;
    name: string;
    color: string;
};

export type SidebarProject = ProjectSummary & {
    requests_count: number;
};

export type Project = ProjectSummary & {
    description: string | null;
    hidden_from_clients: boolean;
    archived_at: string | null;
    canDelete: boolean;
};

export type ProjectStats = ProjectSummary & {
    description: string | null;
    tasksCount: number;
    doneTasksCount: number;
    requestsCount: number;
};

export type Status = {
    id: number;
    name: string;
    color: string;
    is_done: boolean;
};

export type CustomFieldType =
    | 'text'
    | 'number'
    | 'date'
    | 'dropdown'
    | 'checkbox'
    | 'url';

export type CustomFieldTypeOption = {
    value: CustomFieldType;
    label: string;
};

export type CustomFieldOption = {
    id: string;
    name: string;
    color: string;
};

export type CustomField = {
    id: number;
    name: string;
    type: CustomFieldType;
    options: CustomFieldOption[];
    hidden_from_clients: boolean;
};

export type CustomFieldValue = string | number | boolean;

export type Member = {
    id: number;
    name: string;
    email: string | null;
    avatar?: string | null;
    isClient: boolean;
};

export type Priority = 1 | 2 | 3 | 4;

export type PriorityOption = {
    value: Priority;
    label: string;
};

export type Task = {
    id: number;
    project_id: number;
    status_id: number;
    needs_triage: boolean;
    title: string;
    description: string | null;
    priority: Priority | null;
    assignee_id: number | null;
    due_date: string | null;
    start_date: string | null;
    is_milestone: boolean;
    /** 0 to 100, set by hand; done tasks count as 100. */
    progress: number;
    position: number;
    created_by: { id: number; name: string } | null;
    completed_at: string | null;
    comments_count: number;
    subtasks_count: number;
    subtasks_done_count: number;
    attachments_count: number;
    /** Values keyed by custom field id; unset fields are missing. */
    custom_fields: Record<number, CustomFieldValue>;
    created_at: string;
    updated_at: string;
};

export type Subtask = {
    id: number;
    title: string;
    assignee_id: number | null;
    due_date: string | null;
    completed_at: string | null;
};

export type SubtaskInput = Partial<
    Pick<Subtask, 'title' | 'assignee_id' | 'due_date'> & { completed: boolean }
>;

export type Attachment = {
    id: number;
    name: string;
    size: number;
    mime_type: string | null;
    user: { id: number; name: string } | null;
    created_at: string;
    download_url: string;
    preview_url: string | null;
};

export type Activity = {
    id: number;
    type: string;
    data: Record<string, unknown>;
    user: { id: number; name: string } | null;
    created_at: string;
};

export type Comment = {
    id: number;
    body: string;
    is_internal: boolean;
    user: { id: number; name: string } | null;
    created_at: string;
};

export type TaskInput = Partial<
    Pick<
        Task,
        | 'title'
        | 'description'
        | 'status_id'
        | 'priority'
        | 'assignee_id'
        | 'due_date'
        | 'start_date'
        | 'is_milestone'
        | 'progress'
    >
> & { custom_fields?: Record<number, CustomFieldValue | null> };

export type DashboardTask = Task & {
    project: ProjectSummary;
    status: Status;
};
