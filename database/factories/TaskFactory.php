<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status_id' => fn (array $attributes) => Project::find($attributes['project_id'])->defaultStatus()->id,
            'title' => rtrim(fake()->sentence(fake()->numberBetween(3, 7)), '.'),
            'description' => fake()->optional()->paragraph(),
            'priority' => fake()->optional()->randomElement(TaskPriority::cases()),
            'due_date' => fake()->optional()->dateTimeBetween('-1 week', '+3 weeks'),
            'position' => fake()->numberBetween(1, 1000),
        ];
    }
}
