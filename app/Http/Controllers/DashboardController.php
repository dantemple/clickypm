<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;
        $isClient = $request->user()->isClientOf($team);

        $projects = $team->projects()
            ->active()
            ->visibleTo($isClient)
            ->withCount([
                'tasks' => fn ($query) => $query->where('needs_triage', false),
                'tasks as done_tasks_count' => fn ($query) => $query->where('needs_triage', false)->whereNotNull('completed_at'),
                'tasks as requests_count' => fn ($query) => $query->where('needs_triage', true),
            ])
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'description' => $project->description,
                'tasksCount' => $project->tasks_count,
                'doneTasksCount' => $project->done_tasks_count,
                'requestsCount' => $project->requests_count,
            ]);

        $myTasks = Task::query()
            ->with(['project:id,name,color', 'status:id,name,color,is_done', 'creator:id,name'])
            ->whereHas('project', fn ($query) => $query->where('team_id', $team->id)->active()->visibleTo($isClient))
            ->where('assignee_id', $request->user()->id)
            ->whereNull('completed_at')
            ->orderByRaw('due_date IS NULL, due_date')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (Task $task) => [
                ...$task->toTaskData(),
                'project' => $task->project->only(['id', 'name', 'color']),
                'status' => $task->status->only(['id', 'name', 'color', 'is_done']),
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'projectStats' => $projects,
            'myTasks' => $myTasks,
        ]);
    }
}
