<?php

namespace Tests\Feature\Projects;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_are_created_in_the_first_open_status_at_the_end_of_the_list()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        Task::factory()->for($project)->create(['position' => 5]);

        $this->actingAs($user)
            ->post(route('projects.tasks.store', [$user->currentTeam, $project]), ['title' => 'Write copy'])
            ->assertRedirect();

        $task = $project->tasks()->latest('id')->firstOrFail();

        $this->assertSame('Write copy', $task->title);
        $this->assertSame('To do', $task->status->name);
        $this->assertSame(6, $task->position);
        $this->assertSame($user->id, $task->created_by);
    }

    public function test_tasks_can_be_created_straight_into_a_group()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $review = $project->statuses->firstWhere('name', 'Review');

        $this->actingAs($user)
            ->post(route('projects.tasks.store', [$user->currentTeam, $project]), [
                'title' => 'Check links',
                'status_id' => $review->id,
                'priority' => TaskPriority::High->value,
                'assignee_id' => $user->id,
            ])
            ->assertRedirect();

        $task = $project->tasks()->firstOrFail();

        $this->assertSame($review->id, $task->status_id);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame($user->id, $task->assignee_id);
    }

    public function test_a_single_field_can_be_updated_inline()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['title' => 'Original', 'due_date' => null]);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['due_date' => '2026-11-01'])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame('Original', $task->title);
        $this->assertSame('2026-11-01', $task->due_date->toDateString());
    }

    public function test_moving_a_task_into_a_done_status_completes_it()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $done = $project->statuses->firstWhere('is_done', true);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['status_id' => $done->id]);

        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['status_id' => $project->defaultStatus()->id]);

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_status_and_assignee_must_belong_to_the_project_and_workspace()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $foreignStatus = Project::factory()->create()->statuses->first();
        $stranger = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), [
                'status_id' => $foreignStatus->id,
                'assignee_id' => $stranger->id,
            ])
            ->assertSessionHasErrors(['status_id', 'assignee_id']);
    }

    public function test_tasks_from_another_project_cannot_be_updated_through_this_one()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $otherTask = Task::factory()->create();

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $otherTask]), ['title' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_deleted_tasks_go_to_the_trash()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->delete(route('projects.tasks.destroy', [$user->currentTeam, $project, $task]))
            ->assertRedirect();

        $this->assertSoftDeleted($task);
    }
}
