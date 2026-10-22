<?php

namespace Tests\Feature\Projects;

use App\Enums\TaskPriority;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityAndSubtaskTest extends TestCase
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

    /**
     * @return array<int, string>
     */
    private function activityTypes(Task $task): array
    {
        return $task->activities()->pluck('type')->all();
    }

    public function test_task_changes_are_recorded_with_who_made_them()
    {
        $task = Task::factory()->for($this->project)->create(['priority' => null, 'due_date' => null, 'assignee_id' => null]);
        [$todo, , $review] = $this->project->statuses;

        $this->actingAs($this->owner)
            ->patch(route('projects.tasks.update', [$this->team, $this->project, $task]), [
                'status_id' => $review->id,
                'assignee_id' => $this->owner->id,
                'priority' => TaskPriority::High->value,
                'due_date' => '2026-11-05',
                'title' => 'Renamed',
            ]);

        $activity = $task->activities()->get()->keyBy('type');

        $this->assertSame(['created', 'status', 'assigned', 'priority', 'due_date', 'renamed'], $this->activityTypes($task));
        $this->assertSame($this->owner->id, $activity['status']->user_id);
        $this->assertSame(['name' => $todo->name, 'color' => $todo->color], $activity['status']->data['from']);
        $this->assertSame('Review', $activity['status']->data['to']['name']);
        $this->assertSame('Dan', $activity['assigned']->data['name']);
        $this->assertSame(['from' => null, 'to' => 'High'], $activity['priority']->data);
        $this->assertSame('2026-11-05', $activity['due_date']->data['to']);
    }

    public function test_requests_and_their_acceptance_are_recorded()
    {
        $client = User::factory()->create();
        $this->team->members()->attach($client, ['role' => TeamRole::Client->value]);

        $this->actingAs($client)
            ->post(route('projects.tasks.store', [$this->team, $this->project]), ['title' => 'Please add a map']);

        $task = $this->project->tasks()->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('projects.tasks.accept', [$this->team, $this->project, $task]));

        $this->assertSame(['requested', 'accepted'], $this->activityTypes($task));
        $this->assertSame($client->id, $task->activities()->first()->user_id);
    }

    public function test_subtasks_can_be_added_ticked_edited_and_removed()
    {
        $task = Task::factory()->for($this->project)->create();
        $route = fn (string $name, ?Subtask $subtask = null) => route($name, array_filter([$this->team, $this->project, $task, $subtask]));

        $this->actingAs($this->owner)->post($route('projects.tasks.subtasks.store'), ['title' => 'Write copy'])->assertRedirect();
        $subtask = $task->subtasks()->firstOrFail();

        $this->actingAs($this->owner)->patch($route('projects.tasks.subtasks.update', $subtask), [
            'completed' => true,
            'assignee_id' => $this->owner->id,
            'due_date' => '2026-11-01',
        ])->assertRedirect();

        $subtask->refresh();
        $this->assertNotNull($subtask->completed_at);
        $this->assertSame($this->owner->id, $subtask->assignee_id);

        $this->actingAs($this->owner)->patch($route('projects.tasks.subtasks.update', $subtask), ['completed' => false]);
        $this->assertNull($subtask->fresh()->completed_at);

        $this->actingAs($this->owner)->delete($route('projects.tasks.subtasks.destroy', $subtask))->assertRedirect();
        $this->assertModelMissing($subtask);

        $this->assertSame(
            ['created', 'subtask_added', 'subtask_completed', 'subtask_reopened', 'subtask_removed'],
            $this->activityTypes($task),
        );
    }

    public function test_the_project_page_sends_subtask_progress_and_the_open_tasks_history()
    {
        $task = Task::factory()->for($this->project)->create();
        $task->subtasks()->createMany([
            ['title' => 'One', 'completed_at' => now()],
            ['title' => 'Two'],
        ]);

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first(), 'task' => $task->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.0.subtasks_count', 2)
                ->where('tasks.0.subtasks_done_count', 1)
                ->has('subtasks', 2)
                ->where('subtasks.0.title', 'One')
                ->where('activity.0.type', 'created'),
            );
    }

    public function test_clients_see_subtasks_and_history_but_cannot_change_subtasks()
    {
        $client = User::factory()->create();
        $this->team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $task = Task::factory()->for($this->project)->create();
        $subtask = $task->subtasks()->create(['title' => 'One']);

        $this->actingAs($client)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first(), 'task' => $task->id]))
            ->assertInertia(fn (Assert $page) => $page->has('subtasks', 1)->has('activity', 2));

        $this->actingAs($client)
            ->post(route('projects.tasks.subtasks.store', [$this->team, $this->project, $task]), ['title' => 'Sneaky'])
            ->assertForbidden();
        $this->actingAs($client)
            ->patch(route('projects.tasks.subtasks.update', [$this->team, $this->project, $task, $subtask]), ['completed' => true])
            ->assertForbidden();
    }

    public function test_subtasks_are_scoped_to_their_task()
    {
        $task = Task::factory()->for($this->project)->create();
        $otherSubtask = Task::factory()->for($this->project)->create()->subtasks()->create(['title' => 'Elsewhere']);

        $this->actingAs($this->owner)
            ->patch(route('projects.tasks.subtasks.update', [$this->team, $this->project, $task, $otherSubtask]), ['title' => 'Hijacked'])
            ->assertNotFound();
    }
}
