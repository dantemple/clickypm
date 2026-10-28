<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can create projects in the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team) && ! $user->isClientOf($team);
    }

    /**
     * Determine whether the user can view the project, comment on its tasks and request new ones.
     */
    public function view(User $user, Project $project): bool
    {
        if (! $user->belongsToTeam($project->team)) {
            return false;
        }

        return ! $project->hidden_from_clients || ! $user->isClientOf($project->team);
    }

    /**
     * Determine whether the user can edit the project, its views, statuses and tasks,
     * and triage client requests.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team) && ! $user->isClientOf($project->team);
    }

    /**
     * Determine whether the user can move the project to another workspace:
     * taking it out of this one is as big a deal as deleting it, and they
     * must be able to create projects in the destination.
     */
    public function move(User $user, Project $project, Team $destination): bool
    {
        return $destination->isNot($project->team)
            && $this->delete($user, $project)
            && $this->create($user, $destination);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasTeamPermission($project->team, TeamPermission::UpdateTeam);
    }
}
