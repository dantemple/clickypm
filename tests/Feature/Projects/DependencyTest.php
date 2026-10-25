<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DependencyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->team->update(['features' => ['gantt' => true]]);
        $this->project = Project::factory()->for($this->team)->create();
    }

    private function link(Task $successor, Task $predecessor, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)
            ->post(route('projects.tasks.dependencies.store', [$this->team, $this->project, $successor]), ['predecessor_id' => $predecessor->id]);
    }

    public function test_tasks_can_be_linked_and_unlinked()
    {
        [$first, $second] = Task::factory()->count(2)->for($this->project)->create();

        $this->link($second, $first)->assertRedirect()->assertSessionHasNoErrors();

        $dependency = TaskDependency::firstOrFail();
        $this->assertSame($first->id, $dependency->predecessor_id);
        $this->assertSame($second->id, $dependency->successor_id);
        $this->assertSame('finish_to_start', $dependency->type);
        $this->assertSame('blocked_by_added', $second->activities()->pluck('type')->last());
        $this->assertSame('blocks_added', $first->activities()->pluck('type')->last());

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('dependencies', 1)
                ->where('dependencies.0.predecessor_id', $first->id)
                ->where('dependencies.0.successor_id', $second->id));

        $this->actingAs($this->owner)
            ->delete(route('projects.dependencies.destroy', [$this->team, $this->project, $dependency]))
            ->assertRedirect();

        $this->assertDatabaseCount('task_dependencies', 0);
        $this->assertSame('blocked_by_removed', $second->activities()->pluck('type')->last());
    }

    public function test_links_stay_within_a_project_and_never_loop()
    {
        [$a, $b, $c] = Task::factory()->count(3)->for($this->project)->create();
        $elsewhere = Task::factory()->for(Project::factory()->for($this->team))->create();

        $this->link($a, $a)->assertSessionHasErrors('predecessor_id');
        $this->link($a, $elsewhere)->assertSessionHasErrors('predecessor_id');

        $this->link($b, $a)->assertSessionHasNoErrors();
        $this->link($b, $a)->assertSessionHasErrors('predecessor_id');
        $this->link($a, $b)->assertSessionHasErrors('predecessor_id');

        $this->link($c, $b)->assertSessionHasNoErrors();
        $this->link($a, $c)->assertSessionHasErrors('predecessor_id');

        $this->assertDatabaseCount('task_dependencies', 2);
    }

    public function test_links_are_only_sent_while_the_module_is_on()
    {
        [$first, $second] = Task::factory()->count(2)->for($this->project)->create();
        $this->link($second, $first);
        $this->team->update(['features' => []]);

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page->has('dependencies', 0));
    }

    public function test_links_to_trashed_tasks_disappear_and_return()
    {
        [$first, $second] = Task::factory()->count(2)->for($this->project)->create();
        $this->link($second, $first);
        $first->moveToTrash($this->owner);

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page->has('dependencies', 0));

        $first->restore();

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page->has('dependencies', 1));
    }

    public function test_clients_cannot_link_tasks()
    {
        $client = User::factory()->create();
        $this->team->members()->attach($client, ['role' => TeamRole::Client->value]);
        [$first, $second] = Task::factory()->count(2)->for($this->project)->create();

        $this->link($second, $first, $client)->assertForbidden();
    }
}
