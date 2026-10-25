<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TaskPriority;
use App\Enums\TeamFeature;
use App\Enums\TeamRole;
use App\Enums\ViewType;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectView;
use App\Models\Status;
use App\Models\TaskActivity;
use App\Models\TaskDependency;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectViewController extends Controller
{
    /**
     * Columns a table view can show or sort by.
     */
    private const COLUMNS = ['status', 'assignee', 'priority', 'due_date', 'created_at'];

    /**
     * Ids of the workspace fields clients may see, once looked up.
     *
     * @var array<int, int>|null
     */
    private ?array $clientFieldIds = null;

    /**
     * Show a project through one of its saved views.
     */
    public function show(Request $request, Team $current_team, Project $project, ProjectView $view): Response|RedirectResponse
    {
        Gate::authorize('view', $project);

        // Views of a module that's switched off stay stored but aren't shown.
        if (! $this->available($view, $current_team)) {
            return to_route('projects.show', [$current_team, $project]);
        }

        $user = $request->user();
        $isClient = $user->isClientOf($current_team);
        $openTask = $request->filled('task') ? $project->tasks()->find($request->integer('task')) : null;
        $customFields = $project->customFields()->visibleTo($isClient)->get();

        $tasks = fn (bool $requests) => $project->tasks()
            ->where('needs_triage', $requests)
            ->with([
                'creator:id,name',
                'customFieldValues' => fn ($query) => $query->whereIn('custom_field_id', $customFields->modelKeys()),
            ])
            ->withCount([
                'comments' => fn ($query) => $query->visibleTo($isClient),
                'subtasks',
                'attachments',
                'subtasks as subtasks_done_count' => fn ($query) => $query->whereNotNull('completed_at'),
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map->toTaskData();

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'description' => $project->description,
                'hidden_from_clients' => $project->hidden_from_clients,
                'archived_at' => $project->archived_at?->toIso8601String(),
                'canDelete' => $user->can('delete', $project),
            ],
            'canEdit' => $user->can('update', $project),
            'views' => $project->views->filter(fn (ProjectView $view) => $this->available($view, $current_team))->values()->map->toViewData(),
            'view' => $view->toViewData(),
            'statuses' => $project->statuses->map(fn (Status $status) => $status->only(['id', 'name', 'color', 'is_done'])),
            'customFields' => $customFields->map->toFieldData(),
            // Everything the workspace defines, for choosing what the project uses.
            'workspaceFields' => fn () => $user->can('update', $project)
                ? $current_team->customFields->map->toFieldData()
                : [],
            'canManageFields' => $user->can('update', $current_team),
            'tasks' => fn () => $tasks(false),
            'requests' => fn () => $tasks(true),
            // Links between the project's tasks, for the Gantt chart and the
            // task panel. Only the Gantt module uses them.
            'dependencies' => fn () => $current_team->hasFeature(TeamFeature::Gantt)
                ? TaskDependency::query()
                    ->whereHas('successor', fn ($query) => $query->where('project_id', $project->id))
                    ->whereHas('predecessor')
                    ->get()
                    ->map->toDependencyData()
                : [],
            'openTaskId' => $openTask?->id,
            'comments' => fn () => $openTask
                ? $openTask->comments()->visibleTo($isClient)->with('user:id,name')->get()->map->toCommentData()
                : [],
            'subtasks' => fn () => $openTask?->subtasks->map->toSubtaskData() ?? [],
            'attachments' => fn () => $openTask
                ? $openTask->setRelation('project', $project->setRelation('team', $current_team))
                    ->attachments()->with('user:id,name')->get()
                    ->each->setRelation('task', $openTask)
                    ->map->toAttachmentData()
                : [],
            'maxUploadMegabytes' => Attachment::MAX_KILOBYTES / 1024,
            'activity' => fn () => $openTask
                ? $openTask->activities()->with('user:id,name')->get()
                    ->when($isClient, fn ($activities) => $activities->reject(
                        fn (TaskActivity $activity) => $this->hiddenFromClients($activity, $current_team),
                    ))
                    ->map->toActivityData()->values()
                : [],
            'members' => $current_team->members()
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $isClient ? null : $member->email,
                    'avatar' => $member->avatar ?? null,
                    'isClient' => $member->getRelation('pivot')->role === TeamRole::Client,
                ]),
            'priorities' => TaskPriority::options(),
        ]);
    }

    /**
     * Whether the view belongs to a module the workspace has switched on.
     */
    private function available(ProjectView $view, Team $team): bool
    {
        return $view->type !== ViewType::Gantt || $team->hasFeature(TeamFeature::Gantt);
    }

    /**
     * Whether a history entry is about a custom field clients can't see,
     * including fields that have since been deleted.
     */
    private function hiddenFromClients(TaskActivity $activity, Team $team): bool
    {
        if ($activity->type !== 'custom_field') {
            return false;
        }

        $this->clientFieldIds ??= $team->customFields()->visibleTo(true)->pluck('id')->all();

        return ! in_array($activity->data['field_id'] ?? null, $this->clientFieldIds, true);
    }

    /**
     * Add a new view tab to the project.
     */
    public function store(Request $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        // The Gantt type is only on offer once the module is switched on.
        $type = Rule::enum(ViewType::class);

        if (! $current_team->hasFeature(TeamFeature::Gantt)) {
            $type->except(ViewType::Gantt);
        }

        $validated = $request->validate([
            'type' => ['required', $type],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $type = ViewType::from($validated['type']);

        $view = $project->views()->create([
            'name' => $validated['name'] ?? $type->label(),
            'type' => $type,
            'position' => (int) $project->views()->max('position') + 1,
        ]);

        return to_route('projects.views.show', [$current_team, $project, $view]);
    }

    /**
     * Rename a view or save its settings (grouping, sorting, columns, filters).
     */
    public function update(Request $request, Team $current_team, Project $project, ProjectView $view): RedirectResponse
    {
        Gate::authorize('update', $project);

        // Custom fields the project uses can be shown and sorted as columns too.
        $columns = [...self::COLUMNS, ...$project->customFields()->pluck('custom_fields.id')->map(fn (int $id) => "field_{$id}")];

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'settings' => ['sometimes', 'array'],
            'settings.show_closed' => ['sometimes', 'boolean'],
            'settings.zoom' => ['sometimes', Rule::in(['day', 'week', 'month'])],
            'settings.group_by' => ['sometimes', Rule::in(['status', 'priority', 'assignee'])],
            'settings.sort' => ['sometimes', 'array'],
            'settings.sort.column' => ['required_with:settings.sort', Rule::in(['position', 'title', ...$columns])],
            'settings.sort.direction' => ['required_with:settings.sort', Rule::in(['asc', 'desc'])],
            'settings.columns' => ['sometimes', 'array'],
            'settings.columns.*' => [Rule::in($columns)],
        ]);

        if (isset($validated['name'])) {
            $view->name = $validated['name'];
        }

        if (isset($validated['settings'])) {
            $view->settings = [...$view->settings, ...$validated['settings']];
        }

        $view->save();

        return back();
    }

    /**
     * Remove a view tab. A project always keeps at least one view.
     */
    public function destroy(Team $current_team, Project $project, ProjectView $view): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ($project->views()->count() <= 1) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A project needs at least one view.')]);

            return back();
        }

        $view->delete();

        return to_route('projects.views.show', [$current_team, $project, $project->views()->first()]);
    }
}
