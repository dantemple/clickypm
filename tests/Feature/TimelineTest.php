<?php

namespace Tests\Feature;

use App\Actions\Tasks\LinkTasks;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_timeline_needs_the_gantt_module()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('timeline', $user->currentTeam))->assertNotFound();

        $user->currentTeam->update(['features' => ['gantt' => true]]);

        $this->actingAs($user)->get(route('timeline', $user->currentTeam))->assertOk();
    }

    public function test_it_shows_every_visible_project_with_its_dated_tasks()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $team->update(['features' => ['gantt' => true]]);
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);

        $site = Project::factory()->for($team)->create(['name' => 'Hutwood']);
        $hidden = Project::factory()->for($team)->create(['name' => 'Internal', 'hidden_from_clients' => true]);
        $dated = Task::factory()->for($site)->create(['due_date' => '2026-11-10', 'start_date' => '2026-11-01']);
        $later = Task::factory()->for($site)->create(['due_date' => '2026-11-20']);
        Task::factory()->for($site)->create(['due_date' => null]);
        Task::factory()->for($site)->create(['due_date' => '2026-11-12', 'needs_triage' => true]);
        Task::factory()->for($hidden)->create(['due_date' => '2026-11-15']);
        app(LinkTasks::class)->handle($later, $dated);

        $this->actingAs($owner)
            ->get(route('timeline', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->component('timeline')
                ->has('projects', 2)
                ->has('tasks', 3)
                ->has('dependencies', 1)
                ->where('canEdit', true));

        $this->actingAs($client)
            ->get(route('timeline', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->has('tasks', 2)
                ->where('canEdit', false));
    }
}
