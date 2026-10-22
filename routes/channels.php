<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Live updates only ever say "something changed"; the page then re-fetches
// through the normal, permission-checked routes. So these channels only
// need the same rule as viewing the project or being in the workspace.
Broadcast::channel('projects.{project}', function (User $user, Project $project) {
    return $user->can('view', $project);
});

// By id rather than slug: slugs change when a workspace is renamed.
Broadcast::channel('workspaces.{teamId}', function (User $user, int $teamId) {
    return $user->teams()->whereKey($teamId)->exists();
});
