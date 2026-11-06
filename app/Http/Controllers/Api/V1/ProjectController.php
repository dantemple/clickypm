<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Projects\CreateProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\SaveProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    /**
     * The workspace's projects the caller may see. Archived projects are
     * left out unless asked for with ?include_archived=1.
     */
    public function index(Request $request, Team $workspace): AnonymousResourceCollection
    {
        abort_unless($request->user()->belongsToTeam($workspace), 403);

        $projects = $workspace->projects()
            ->visibleTo($request->user()->isClientOf($workspace))
            ->unless($request->boolean('include_archived'), fn ($query) => $query->active())
            ->with(['statuses', 'tags'])
            ->get();

        return ProjectResource::collection($projects);
    }

    /**
     * Create a project in the workspace.
     */
    public function store(SaveProjectRequest $request, Team $workspace, CreateProject $createProject): JsonResponse
    {
        Gate::authorize('create', [Project::class, $workspace]);

        $project = $createProject->handle(
            $request->user(),
            $workspace,
            $request->validated('name'),
            $request->validated('color'),
            $request->validated('description'),
            $request->validated('key'),
        );

        return (new ProjectResource($project->load(['statuses', 'tags'])))->response()->setStatusCode(201);
    }

    /**
     * One project, with its statuses and tags.
     */
    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($project->load(['statuses', 'tags']));
    }

    /**
     * Change one or more of the project's details.
     */
    public function update(SaveProjectRequest $request, Project $project): ProjectResource
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource($project->load(['statuses', 'tags']));
    }
}
