<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_can_have_a_start_date_and_be_milestones()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $this->actingAs($user)
            ->post(route('projects.tasks.store', [$user->currentTeam, $project]), [
                'title' => 'Crane lift',
                'start_date' => '2026-10-01',
                'due_date' => '2026-10-02',
                'is_milestone' => true,
            ])
            ->assertSessionHasNoErrors();

        $task = $project->tasks()->firstOrFail();

        $this->assertSame('2026-10-01', $task->start_date->toDateString());
        $this->assertTrue($task->is_milestone);
        $this->assertSame('2026-10-01', $task->toTaskData()['start_date']);
        $this->assertTrue($task->toTaskData()['is_milestone']);
    }

    public function test_a_task_cannot_start_after_it_is_due()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['start_date' => null, 'due_date' => '2026-10-10']);
        $update = route('projects.tasks.update', [$user->currentTeam, $project, $task]);

        // Either date on its own is checked against the one already saved.
        $this->actingAs($user)->patch($update, ['start_date' => '2026-10-11'])->assertSessionHasErrors('start_date');
        $this->actingAs($user)->patch($update, ['start_date' => '2026-10-05'])->assertSessionHasNoErrors();
        $this->actingAs($user)->patch($update, ['due_date' => '2026-10-04'])->assertSessionHasErrors('start_date');

        // Both together are checked against each other.
        $this->actingAs($user)->patch($update, ['start_date' => '2026-11-02', 'due_date' => '2026-11-01'])->assertSessionHasErrors('start_date');
        $this->actingAs($user)->patch($update, ['start_date' => '2026-11-01', 'due_date' => '2026-11-01'])->assertSessionHasNoErrors();

        // Clearing the due date lifts the limit.
        $this->actingAs($user)->patch($update, ['due_date' => null])->assertSessionHasNoErrors();
        $this->actingAs($user)->patch($update, ['start_date' => '2027-01-01'])->assertSessionHasNoErrors();
    }

    public function test_progress_is_a_whole_percentage()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();
        $update = route('projects.tasks.update', [$user->currentTeam, $project, $task]);

        $this->assertSame(0, $task->progress);

        $this->actingAs($user)->patch($update, ['progress' => 45])->assertSessionHasNoErrors();
        $this->assertSame(45, $task->fresh()->progress);
        $this->assertSame(['from' => 0, 'to' => 45], $task->activities()->where('type', 'progress')->first()->data);

        $this->actingAs($user)->patch($update, ['progress' => 101])->assertSessionHasErrors('progress');
        $this->actingAs($user)->patch($update, ['progress' => -1])->assertSessionHasErrors('progress');
        $this->actingAs($user)->patch($update, ['progress' => 'half'])->assertSessionHasErrors('progress');
    }

    public function test_schedule_changes_are_recorded_in_the_history()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['start_date' => null, 'due_date' => '2026-10-10', 'is_milestone' => false]);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['start_date' => '2026-10-01', 'is_milestone' => true])
            ->assertSessionHasNoErrors();

        $types = $task->activities()->pluck('type')->all();

        $this->assertContains('start_date', $types);
        $this->assertContains('milestone', $types);
        $this->assertSame(['from' => null, 'to' => '2026-10-01'], $task->activities()->where('type', 'start_date')->first()->data);
    }
}
