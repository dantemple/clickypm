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
     * Create a project with the default statuses and a List view.
     */
    public function handle(User $user, Team $team, string $name, ?string $color = null, ?string $description = null): Project
    {
        return DB::transaction(function () use ($user, $team, $name, $color, $description) {
            $project = $team->projects()->create([
                ...array_filter(['name' => $name, 'color' => $color, 'description' => $description], fn ($value) => $value !== null),
                'position' => (int) $team->projects()->max('position') + 1,
                'created_by' => $user->id,
            ]);

            foreach (Status::DEFAULTS as $position => $status) {
                $project->statuses()->create([...$status, 'position' => $position]);
            }

            // Everyone starts on a List; Board and Table can be added with "+ View".
            $project->views()->create([
                'name' => ViewType::List->label(),
                'type' => ViewType::List,
                'position' => 0,
            ]);

            return $project;
        });
    }
}
