<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    /**
     * The workspaces the caller belongs to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return WorkspaceResource::collection($request->user()->teams()->orderByRaw('LOWER(teams.name)')->get());
    }

    /**
     * One workspace.
     */
    public function show(Request $request, Team $workspace): WorkspaceResource
    {
        abort_unless($request->user()->belongsToTeam($workspace), 403);

        return new WorkspaceResource($workspace);
    }

    /**
     * Everyone on the workspace, for assigning tasks. Clients don't see the
     * member list, as in the app.
     */
    public function members(Team $workspace): AnonymousResourceCollection
    {
        Gate::authorize('view', $workspace);

        return MemberResource::collection($workspace->members()->orderBy('name')->get());
    }
}
