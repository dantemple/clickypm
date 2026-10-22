<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TaskPriority;
use App\Enums\TeamRole;
use App\Enums\ViewType;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectView;
use App\Models\Status;
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
     * Show a project through one of its saved views.
     */
    public function show(Request $request, Team $current_team, Project $project, ProjectView $view): Response
    {
        Gate::authorize('view', $project);

        $user = $request->user();
        $isClient = $user->isClientOf($current_team);
        $openTask = $request->filled('task') ? $project->tasks()->find($request->integer('task')) : null;

        $tasks = fn (bool $requests) => $project->tasks()
            ->where('needs_triage', $requests)
            ->with('creator:id,name')
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
                'canDelete' => $user->can('delete', $project),
            ],
            'canEdit' => $user->can('update', $project),
            'views' => $project->views->map->toViewData(),
            'view' => $view->toViewData(),
            'statuses' => $project->statuses->map(fn (Status $status) => $status->only(['id', 'name', 'color', 'is_done'])),
            'tasks' => fn () => $tasks(false),
            'requests' => fn () => $tasks(true),
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
                ? $openTask->activities()->with('user:id,name')->get()->map->toActivityData()
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
     * Add a new view tab to the project.
     */
    public function store(Request $request, Team $current_team, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'type' => ['required', Rule::enum(ViewType::class)],
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

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'settings' => ['sometimes', 'array'],
            'settings.show_closed' => ['sometimes', 'boolean'],
            'settings.group_by' => ['sometimes', Rule::in(['status', 'priority', 'assignee'])],
            'settings.sort' => ['sometimes', 'array'],
            'settings.sort.column' => ['required_with:settings.sort', Rule::in(['position', 'title', ...self::COLUMNS])],
            'settings.sort.direction' => ['required_with:settings.sort', Rule::in(['asc', 'desc'])],
            'settings.columns' => ['sometimes', 'array'],
            'settings.columns.*' => [Rule::in(self::COLUMNS)],
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
