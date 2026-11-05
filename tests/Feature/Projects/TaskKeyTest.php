<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\MoveProject;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_get_a_key_from_their_name_and_tasks_are_numbered()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)->post(route('projects.store', $team), ['name' => 'Website Relaunch']);
        $this->actingAs($user)->post(route('projects.store', $team), ['name' => 'Weekly Reports']);
        $this->actingAs($user)->post(route('projects.store', $team), ['name' => 'Marketing']);

        [$website, $reports, $marketing] = $team->projects()->get();

        $this->assertSame('WR', $website->key);
        $this->assertSame('WR2', $reports->key);
        $this->assertSame('MAR', $marketing->key);

        $this->actingAs($user)->post(route('projects.tasks.store', [$team, $website]), ['title' => 'First']);
        $this->actingAs($user)->post(route('projects.tasks.store', [$team, $website]), ['title' => 'Second']);
        $this->actingAs($user)->post(route('projects.tasks.store', [$team, $reports]), ['title' => 'Elsewhere']);

        $this->assertSame([1, 2], $website->tasks()->orderBy('id')->pluck('number')->all());
        $this->assertSame([1], $reports->tasks()->pluck('number')->all());

        // Trashed tasks keep their number, so the next task doesn't reuse it.
        $this->actingAs($user)->delete(route('projects.tasks.destroy', [$team, $website, $website->tasks()->latest('id')->first()]));
        $this->actingAs($user)->post(route('projects.tasks.store', [$team, $website]), ['title' => 'Third']);

        $this->assertSame(3, $website->tasks()->latest('id')->first()->number);
    }

    public function test_project_keys_can_be_changed_within_limits()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create(['name' => 'Website']);
        $other = Project::factory()->for($team)->create(['name' => 'Mobile app', 'key' => 'APP']);

        $this->actingAs($user)
            ->patch(route('projects.update', [$team, $project]), ['name' => 'Website', 'key' => 'web'])
            ->assertSessionHasNoErrors();
        $this->assertSame('WEB', $project->fresh()->key);

        $this->actingAs($user)
            ->patch(route('projects.update', [$team, $project]), ['name' => 'Website', 'key' => '1AB'])
            ->assertSessionHasErrors('key');

        $this->actingAs($user)
            ->patch(route('projects.update', [$team, $project]), ['name' => 'Website', 'key' => 'app'])
            ->assertSessionHasErrors('key');

        // Keys only need to be unique within the workspace.
        $elsewhere = Project::factory()->create(['name' => 'Something']);
        $this->assertSame('APP', $other->key);
        $elsewhere->update(['key' => 'APP']);
        $this->assertSame('APP', $elsewhere->fresh()->key);
    }

    public function test_a_moved_project_takes_a_free_key_in_its_new_workspace()
    {
        $user = User::factory()->create();
        $destination = Team::factory()->create();
        $destination->members()->attach($user, ['role' => 'owner']);
        Project::factory()->for($destination)->create(['key' => 'WEB']);
        $project = Project::factory()->for($user->currentTeam)->create(['key' => 'WEB']);

        app(MoveProject::class)->handle($project, $destination);

        $this->assertSame('WEB2', $project->fresh()->key);
    }

    public function test_keys_are_sent_to_the_project_page_and_found_by_search()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create(['key' => 'WEB']);
        [$first, $second] = Task::factory()->count(2)->for($project)->sequence(['position' => 1], ['position' => 2])->create();

        $this->actingAs($user)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.key', 'WEB')
                ->where('tasks.0.number', 1)
                ->where('tasks.1.number', 2));

        $this->actingAs($user)
            ->getJson(route('search', [$team, 'q' => 'web-2']))
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.id', $second->id)
            ->assertJsonPath('tasks.0.number', 2)
            ->assertJsonPath('tasks.0.project.key', 'WEB');

        $this->assertSame(1, $first->number);
    }
}
