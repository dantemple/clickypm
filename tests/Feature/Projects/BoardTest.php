<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->project = Project::factory()->for($this->team)->create();
    }

    /**
     * @param  array<int, int>  $order
     */
    private function move(Task $task, int $statusId, array $order, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->owner)
            ->post(route('projects.tasks.move', [$this->team, $this->project, $task]), [
                'status_id' => $statusId,
                'order' => $order,
            ]);
    }

    /**
     * @return array<int, int>
     */
    private function columnOrder(int $statusId): array
    {
        return $this->project->tasks()->where('status_id', $statusId)->orderBy('position')->pluck('id')->all();
    }

    public function test_dragging_a_card_to_another_column_changes_its_status_and_places_it()
    {
        [$todo, $progress] = $this->project->statuses;
        [$a, $b] = Task::factory(2)->for($this->project)->sequence(['position' => 1], ['position' => 2])->create(['status_id' => $progress->id]);
        $moving = Task::factory()->for($this->project)->create(['status_id' => $todo->id, 'position' => 3]);

        $this->move($moving, $progress->id, [$a->id, $moving->id, $b->id])->assertRedirect();

        $this->assertSame($progress->id, $moving->fresh()->status_id);
        $this->assertSame([$a->id, $moving->id, $b->id], $this->columnOrder($progress->id));
        $this->assertSame('status', $moving->activities()->reorder('id', 'desc')->value('type'));
    }

    public function test_cards_can_be_reordered_within_a_column_without_adding_history()
    {
        $todo = $this->project->statuses->first();
        [$a, $b, $c] = Task::factory(3)->for($this->project)
            ->sequence(['position' => 10], ['position' => 20], ['position' => 30])
            ->create(['status_id' => $todo->id]);

        $this->move($c, $todo->id, [$c->id, $a->id, $b->id])->assertRedirect();

        $this->assertSame([$c->id, $a->id, $b->id], $this->columnOrder($todo->id));
        $this->assertSame([10, 20, 30], $this->project->tasks()->orderBy('position')->pluck('position')->all());
        $this->assertSame(['created'], $c->activities()->pluck('type')->all());
    }

    public function test_tied_positions_still_produce_the_requested_order()
    {
        $todo = $this->project->statuses->first();
        [$a, $b] = Task::factory(2)->for($this->project)->create(['status_id' => $todo->id, 'position' => 5]);

        $this->move($b, $todo->id, [$b->id, $a->id]);

        $this->assertSame([$b->id, $a->id], $this->columnOrder($todo->id));
    }

    public function test_moves_are_validated_against_the_project()
    {
        $todo = $this->project->statuses->first();
        $task = Task::factory()->for($this->project)->create();
        $other = Project::factory()->for($this->team)->create();
        $foreignTask = Task::factory()->for($other)->create();

        $this->move($task, $other->statuses->first()->id, [$task->id])->assertSessionHasErrors('status_id');
        $this->move($task, $todo->id, [$task->id, $foreignTask->id])->assertSessionHasErrors('order.1');
        $this->move($task, $todo->id, [$foreignTask->id])->assertSessionHasErrors('order');
    }

    public function test_clients_cannot_move_cards()
    {
        $client = User::factory()->create();
        $this->team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $task = Task::factory()->for($this->project)->create();

        $this->move($task, $this->project->statuses->last()->id, [$task->id], $client)->assertForbidden();
    }
}
