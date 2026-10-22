<?php

namespace App\Actions\Projects;

use App\Enums\ViewType;
use App\Models\Project;
use App\Models\Status;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    /**
     * Create a project with the default statuses and one view of each type.
     *
     * @param  array{name: string, color?: string|null, description?: string|null}  $attributes
     */
    public function handle(User $user, Team $team, array $attributes): Project
    {
        return DB::transaction(function () use ($user, $team, $attributes) {
            $project = $team->projects()->create([
                ...array_filter($attributes, fn ($value) => $value !== null),
                'position' => (int) $team->projects()->max('position') + 1,
                'created_by' => $user->id,
            ]);

            foreach (Status::DEFAULTS as $position => $status) {
                $project->statuses()->create([...$status, 'position' => $position]);
            }

            foreach (ViewType::cases() as $position => $type) {
                $project->views()->create([
                    'name' => $type->label(),
                    'type' => $type,
                    'position' => $position,
                ]);
            }

            return $project;
        });
    }
}
