<?php

namespace App\Http\Controllers;

use App\Enums\TeamFeature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every project in the workspace on one Gantt chart. Part of the Gantt module.
 */
class TimelineController extends Controller
{
    public function __invoke(Request $request, Team $current_team): Response
    {
        abort_unless($current_team->hasFeature(TeamFeature::Gantt), 404);

        $user = $request->user();
        $isClient = $user->isClientOf($current_team);

        $projects = $current_team->projects()->active()->visibleTo($isClient)->get();

        $tasks = Task::query()
            ->whereIn('project_id', $projects->modelKeys())
            ->where('needs_triage', false)
            ->whereNotNull('due_date')
            ->with('creator:id,name')
            ->orderBy('project_id')
            ->orderBy('position')
            ->get();

        return Inertia::render('timeline', [
            'projects' => $projects->map(fn (Project $project) => $project->only(['id', 'name', 'color'])),
            'tasks' => $tasks->map->toTaskData(),
            'dependencies' => TaskDependency::query()
                ->whereIn('successor_id', $tasks->modelKeys())
                ->whereIn('predecessor_id', $tasks->modelKeys())
                ->get()
                ->map->toDependencyData(),
            'canEdit' => ! $isClient,
        ]);
    }
}
