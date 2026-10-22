<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $colleagues = User::factory(3)->create();

        $workspace = Team::factory()->create(['name' => 'Acme Inc', 'slug' => 'acme-inc']);
        $workspace->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $colleagues->each(fn (User $colleague) => $workspace->members()->attach($colleague, ['role' => TeamRole::Member->value]));

        $user->switchTeam($workspace);

        $members = $colleagues->push($user);

        // A client contact who can see (most of) Acme's projects, comment, and request work.
        $client = User::factory()->create(['name' => 'Clara Client', 'email' => 'client@example.com']);
        $client->personalTeam()->forceDelete(); // invited clients don't get a personal workspace
        $workspace->members()->attach($client, ['role' => TeamRole::Client->value]);
        $client->switchTeam($workspace);

        foreach (['Website relaunch', 'Mobile app', 'Marketing'] as $position => $name) {
            $project = Project::factory()->for($workspace)->create([
                'name' => $name,
                'position' => $position,
                'created_by' => $user->id,
                'hidden_from_clients' => $name === 'Marketing',
            ]);

            $statuses = $project->statuses;

            Task::factory(fake()->numberBetween(10, 18))
                ->for($project)
                ->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
                ->state(function () use ($statuses, $members) {
                    $status = $statuses->random();

                    return [
                        'status_id' => $status->id,
                        'assignee_id' => fake()->boolean(75) ? $members->random()->id : null,
                        'created_by' => $members->random()->id,
                        'completed_at' => $status->is_done ? now()->subDays(fake()->numberBetween(0, 10)) : null,
                    ];
                })
                ->create();

            if ($project->hidden_from_clients) {
                continue;
            }

            $task = $project->tasks()->where('needs_triage', false)->inRandomOrder()->first();
            $task->comments()->createMany([
                ['user_id' => $client->id, 'body' => 'Can we get an update on this one?'],
                ['user_id' => $user->id, 'body' => 'Blocked on the API change; chase backend before replying.', 'is_internal' => true],
                ['user_id' => $user->id, 'body' => 'On it, we should have this ready by the end of the week.'],
            ]);

            foreach (['Add a cookie banner to the site', 'Logo looks blurry on retina screens'] as $title) {
                $project->tasks()->create([
                    'status_id' => $project->defaultStatus()->id,
                    'needs_triage' => true,
                    'title' => $title,
                    'description' => 'Requested by the client.',
                    'position' => (int) $project->tasks()->max('position') + 1,
                    'created_by' => $client->id,
                ]);
            }
        }
    }
}
