<?php

namespace Database\Factories;

use App\Enums\ViewType;
use App\Models\Project;
use App\Models\Status;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->catchPhrase(),
            'color' => fake()->hexColor(),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Give each project the default statuses and views, like CreateProject does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            foreach (Status::DEFAULTS as $position => $status) {
                $project->statuses()->create([...$status, 'position' => $position]);
            }

            foreach (ViewType::cases() as $position => $type) {
                $project->views()->create(['name' => $type->label(), 'type' => $type, 'position' => $position]);
            }
        });
    }
}
