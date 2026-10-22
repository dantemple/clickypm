<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create(['name' => 'Dan']);
        $this->team = $this->owner->currentTeam;
        $this->project = Project::factory()->for($this->team)->create();
    }

    private function member(TeamRole $role): User
    {
        $user = User::factory()->create();
        $this->team->members()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_trashed_tasks_disappear_from_the_project_and_show_in_the_trash()
    {
        [$kept, $trashed] = Task::factory(2)->for($this->project)->create();

        $this->actingAs($this->owner)->delete(route('projects.tasks.destroy', [$this->team, $this->project, $trashed]));

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 1)->where('tasks.0.id', $kept->id));

        $this->actingAs($this->owner)
            ->get(route('trash.index', $this->team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('trash')
                ->has('trashedTasks', 1)
                ->where('trashedTasks.0.id', $trashed->id)
                ->where('trashedTasks.0.deletedBy', 'Dan')
                ->has('projects', 1)
                ->where('canManageProjects', true),
            );

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first(), 'task' => $trashed->id]))
            ->assertInertia(fn (Assert $page) => $page->where('openTaskId', null));
    }

    public function test_tasks_can_be_restored_with_a_history_entry()
    {
        $task = Task::factory()->for($this->project)->create();
        $task->moveToTrash($this->owner);

        $this->actingAs($this->owner)
            ->post(route('trash.tasks.restore', [$this->team, $task]))
            ->assertRedirect();

        $task->refresh();
        $this->assertFalse($task->trashed());
        $this->assertNull($task->deleted_by);
        $this->assertSame('restored', $task->activities()->reorder('id', 'desc')->value('type'));
    }

    public function test_trashed_tasks_can_be_deleted_for_good()
    {
        $task = Task::factory()->for($this->project)->create();
        $task->moveToTrash($this->owner);

        $this->actingAs($this->owner)
            ->delete(route('trash.tasks.destroy', [$this->team, $task]))
            ->assertRedirect();

        $this->assertModelMissing($task);
    }

    public function test_only_trashed_tasks_can_be_deleted_for_good_through_the_trash()
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->owner)
            ->delete(route('trash.tasks.destroy', [$this->team, $task]))
            ->assertNotFound();

        $this->assertModelExists($task);
    }

    public function test_projects_come_back_with_their_tasks()
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->owner)->delete(route('projects.destroy', [$this->team, $this->project]));

        $this->actingAs($this->owner)
            ->get(route('projects.show', [$this->team, $this->project]))
            ->assertNotFound();
        $this->actingAs($this->owner)
            ->get(route('dashboard', $this->team))
            ->assertInertia(fn (Assert $page) => $page->has('projects', 0));
        $this->actingAs($this->owner)
            ->get(route('trash.index', $this->team))
            ->assertInertia(fn (Assert $page) => $page
                ->has('trashedProjects', 1)
                ->where('trashedProjects.0.tasksCount', 1)
                ->has('trashedTasks', 0));

        $this->actingAs($this->owner)
            ->post(route('trash.projects.restore', [$this->team, $this->project]))
            ->assertRedirect();

        $this->assertFalse($this->project->fresh()->trashed());
        $this->assertFalse($task->fresh()->trashed());
    }

    public function test_a_task_in_a_trashed_project_cannot_be_restored_on_its_own()
    {
        $task = Task::factory()->for($this->project)->create();
        $task->moveToTrash($this->owner);
        $this->project->moveToTrash($this->owner);

        $this->actingAs($this->owner)
            ->post(route('trash.tasks.restore', [$this->team, $task]))
            ->assertStatus(422);
    }

    public function test_members_restore_tasks_but_only_admins_restore_projects()
    {
        $member = $this->member(TeamRole::Member);
        $task = Task::factory()->for($this->project)->create();
        $task->moveToTrash($this->owner);

        $this->actingAs($member)->post(route('trash.tasks.restore', [$this->team, $task]))->assertRedirect();

        $this->project->moveToTrash($this->owner);

        $this->actingAs($member)->post(route('trash.projects.restore', [$this->team, $this->project]))->assertForbidden();
        $this->actingAs($member)->delete(route('trash.projects.destroy', [$this->team, $this->project]))->assertForbidden();
        $this->actingAs($member)
            ->get(route('trash.index', $this->team))
            ->assertInertia(fn (Assert $page) => $page->where('canManageProjects', false));
    }

    public function test_clients_have_no_trash()
    {
        $client = $this->member(TeamRole::Client);
        $task = Task::factory()->for($this->project)->create();
        $task->moveToTrash($this->owner);

        $this->actingAs($client)->get(route('trash.index', $this->team))->assertForbidden();
        $this->actingAs($client)->post(route('trash.tasks.restore', [$this->team, $task]))->assertForbidden();
    }

    public function test_trash_from_another_workspace_is_out_of_reach()
    {
        $otherProject = Project::factory()->create();
        $otherTask = Task::factory()->for($otherProject)->create();
        $otherTask->moveToTrash($this->owner);
        $otherProject->moveToTrash($this->owner);

        $this->actingAs($this->owner)->post(route('trash.tasks.restore', [$this->team, $otherTask]))->assertNotFound();
        $this->actingAs($this->owner)->post(route('trash.projects.restore', [$this->team, $otherProject]))->assertNotFound();
    }

    public function test_the_trash_is_emptied_after_thirty_days()
    {
        $old = Task::factory()->for($this->project)->create();
        $recent = Task::factory()->for($this->project)->create();
        $oldProject = Project::factory()->for($this->team)->create();

        $this->travel(-31)->days();
        $old->moveToTrash($this->owner);
        $oldProject->moveToTrash($this->owner);
        $this->travelBack();
        $recent->moveToTrash($this->owner);

        $this->artisan('model:prune', ['--model' => [Project::class, Task::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelMissing($oldProject);
        $this->assertSoftDeleted($recent);
    }

    public function test_removing_a_status_moves_trashed_tasks_too()
    {
        [$todo, , $review] = $this->project->statuses;
        $task = Task::factory()->for($this->project)->create(['status_id' => $review->id]);
        $task->moveToTrash($this->owner);

        $this->actingAs($this->owner)
            ->put(route('projects.statuses.update', [$this->team, $this->project]), [
                'statuses' => $this->project->statuses
                    ->reject(fn ($status) => $status->is($review))
                    ->map(fn ($status) => $status->only(['id', 'name', 'color', 'is_done']))
                    ->values()
                    ->all(),
            ])
            ->assertRedirect();

        $this->assertModelMissing($review);
        $this->assertSame($todo->id, Task::withTrashed()->find($task->id)->status_id);
    }
}
