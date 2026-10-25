<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchivedProjectController extends Controller
{
    /**
     * List the workspace's archived projects, newest first.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user();

        return Inertia::render('projects/archived', [
            // Not 'projects': that name is the sidebar's shared prop.
            'archivedProjects' => $current_team->projects()
                ->archived()
                ->visibleTo($user->isClientOf($current_team))
                ->with('archiver:id,name')
                ->withCount('tasks')
                ->reorder()
                ->latest('archived_at')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'color' => $project->color,
                    'description' => $project->description,
                    'tasksCount' => $project->tasks_count,
                    'archivedBy' => $project->archiver?->name,
                    'archivedAt' => $project->archived_at?->toIso8601String(),
                ]),
            'canUnarchive' => ! $user->isClientOf($current_team),
        ]);
    }
}
