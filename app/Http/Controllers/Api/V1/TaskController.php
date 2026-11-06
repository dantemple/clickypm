<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * The project's tasks in list order, a page at a time.
     *
     * Filters: status_id, assignee_id (or "me"), priority (by name or
     * number), done (0 or 1), needs_triage (0 or 1), search (in the title).
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        // Priorities can be given by name as well as by number.
        if (is_string($request->input('priority')) && ($named = TaskPriority::fromName($request->input('priority')))) {
            $request->merge(['priority' => $named->value]);
        }

        $filters = $request->validate([
            'status_id' => ['sometimes', 'integer'],
            'assignee_id' => ['sometimes', Rule::anyOf([['integer'], ['in:me']])],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'done' => ['sometimes', 'boolean'],
            'needs_triage' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $tasks = $this->query($project, $user)
            ->when(isset($filters['status_id']), fn (Builder $query) => $query->where('status_id', $filters['status_id']))
            ->when(isset($filters['assignee_id']), fn (Builder $query) => $query->where('assignee_id', $filters['assignee_id'] === 'me' ? $user->id : $filters['assignee_id']))
            ->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(isset($filters['done']), fn (Builder $query) => $filters['done'] ? $query->whereNotNull('completed_at') : $query->whereNull('completed_at'))
            ->when(isset($filters['needs_triage']), fn (Builder $query) => $query->where('needs_triage', (bool) $filters['needs_triage']))
            ->when(isset($filters['search']), fn (Builder $query) => $query->whereLike('title', '%'.$filters['search'].'%'))
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    /**
     * Add a task to the end of the project. A client's task waits in the
     * project's request inbox until the team accepts it.
     */
    public function store(SaveTaskRequest $request, Project $project, CreateTask $createTask): JsonResponse
    {
        Gate::authorize('view', $project);

        $task = $createTask->handle(
            $request->user(),
            $project,
            $request->safe()->except(['attachments', 'custom_fields', 'tag_ids', 'new_tags']),
            $request->validated('custom_fields', []),
            $request->validated('tag_ids'),
            $request->validated('new_tags', []),
        );

        return $this->resource($task, $request->user())->response()->setStatusCode(201);
    }

    /**
     * One task.
     */
    public function show(Request $request, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return $this->resource($task, $request->user());
    }

    /**
     * A task by its key, like WEB-42.
     */
    public function showByKey(Request $request, Team $workspace, string $key): TaskResource
    {
        abort_unless($request->user()->belongsToTeam($workspace), 403);

        if (! preg_match('/^([a-z][a-z0-9]*)-(\d+)$/i', $key, $parts)) {
            abort(404, 'Task keys look like WEB-42.');
        }

        $project = $workspace->projects()->where('key', strtoupper($parts[1]))->firstOrFail();
        $task = $project->tasks()->where('number', (int) $parts[2])->firstOrFail();

        Gate::authorize('view', $task);

        return $this->resource($task, $request->user());
    }

    /**
     * Change one or more of a task's fields.
     */
    public function update(SaveTaskRequest $request, Task $task, UpdateTask $updateTask): TaskResource
    {
        Gate::authorize('update', $task->project);

        $updateTask->handle(
            $request->user(),
            $task,
            $request->safe()->except(['attachments', 'custom_fields', 'tag_ids', 'new_tags']),
            $request->validated('custom_fields', []),
            $request->validated('tag_ids'),
            $request->validated('new_tags', []),
        );

        return $this->resource($task, $request->user());
    }

    /**
     * Move a task to the workspace's trash, where it can be restored in the app.
     */
    public function destroy(Request $request, Task $task): Response
    {
        Gate::authorize('update', $task->project);

        $task->moveToTrash($request->user());

        return response()->noContent();
    }

    /**
     * The project's tasks the viewer may see, with everything the resource shows.
     *
     * @return Builder<Task>
     */
    protected function query(Project $project, User $user): Builder
    {
        $isClient = $user->isClientOf($project->team);
        $fieldIds = $project->customFields()->visibleTo($isClient)->pluck('custom_fields.id');

        return Task::query()
            ->where('project_id', $project->id)
            ->visibleTo($user, $isClient)
            ->with([
                'project',
                'status',
                'assignee:id,name',
                'creator:id,name',
                'tags',
                'customFieldValues' => fn ($query) => $query->whereIn('custom_field_id', $fieldIds),
            ])
            ->withCount([
                'comments' => fn ($query) => $query->visibleTo($isClient),
                'subtasks',
                'attachments',
            ]);
    }

    /**
     * One task, freshly loaded with everything the resource shows.
     */
    protected function resource(Task $task, User $user): TaskResource
    {
        return new TaskResource($this->query($task->project, $user)->findOrFail($task->id));
    }
}
