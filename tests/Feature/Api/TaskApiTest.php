<?php

namespace Tests\Feature\Api;

use App\Enums\TaskPriority;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_are_listed_in_order_a_page_at_a_time()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create(['key' => 'WEB']);
        $second = Task::factory()->for($project)->create(['position' => 2, 'title' => 'Second']);
        $first = Task::factory()->for($project)->create(['position' => 1, 'title' => 'First']);
        $first->comments()->create(['user_id' => $user->id, 'body' => 'Hi']);

        Sanctum::actingAs($user, ['read', 'write']);

        $this->getJson(route('api.v1.projects.tasks.index', [$project, 'per_page' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.key', 'WEB-'.$first->number)
            ->assertJsonPath('data.0.status.name', 'To do')
            ->assertJsonPath('data.0.comments_count', 1)
            ->assertJsonPath('data.0.url', $first->url())
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);

        $this->getJson(route('api.v1.projects.tasks.index', [$project, 'per_page' => 1, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);
    }

    public function test_tasks_can_be_filtered()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $done = $project->statuses->firstWhere('is_done', true);
        $mine = Task::factory()->for($project)->create(['title' => 'Write copy', 'assignee_id' => $user->id, 'priority' => TaskPriority::High]);
        $finished = Task::factory()->for($project)->create(['title' => 'Old copy', 'status_id' => $done->id, 'assignee_id' => null, 'priority' => null]);
        Task::factory()->for($project)->create(['title' => 'Pick fonts', 'assignee_id' => null, 'priority' => null]);

        Sanctum::actingAs($user, ['read', 'write']);

        $list = fn (array $filters) => $this->getJson(route('api.v1.projects.tasks.index', [$project, ...$filters]))->assertOk();

        $list(['assignee_id' => 'me'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $list(['assignee_id' => $user->id])->assertJsonCount(1, 'data');
        $list(['search' => 'copy'])->assertJsonCount(2, 'data');
        $list(['done' => 1])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $finished->id);
        $list(['done' => 0])->assertJsonCount(2, 'data');
        $list(['status_id' => $done->id])->assertJsonCount(1, 'data');
        $list(['priority' => 'high'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.priority', 'high');
    }

    public function test_a_task_is_created_with_everything_written_out()
    {
        Notification::fake();

        $user = User::factory()->create(['name' => 'Dan']);
        $project = Project::factory()->for($user->currentTeam)->create(['key' => 'WEB']);
        $review = $project->statuses->firstWhere('name', 'Review');

        Sanctum::actingAs($user, ['read', 'write']);

        $this->postJson(route('api.v1.projects.tasks.store', $project), [
            'title' => 'Check links',
            'description' => 'All of them.',
            'status_id' => $review->id,
            'priority' => 'High',
            'assignee_id' => $user->id,
            'due_date' => '2026-12-01',
            'new_tags' => ['QA'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'WEB-1')
            ->assertJsonPath('data.title', 'Check links')
            ->assertJsonPath('data.status.id', $review->id)
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.assignee.name', 'Dan')
            ->assertJsonPath('data.creator.name', 'Dan')
            ->assertJsonPath('data.due_date', '2026-12-01')
            ->assertJsonPath('data.tags.0.name', 'QA')
            ->assertJsonPath('data.needs_triage', false)
            ->assertJsonPath('data.comments_count', 0);

        $task = $project->tasks()->sole();

        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame($user->id, $task->created_by);
        // History is credited to the token's owner.
        $this->assertSame($user->id, $task->activities()->firstOrFail()->user_id);

        Notification::assertNothingSentTo($user);
    }

    public function test_assigning_someone_else_tells_them()
    {
        Notification::fake();

        $user = User::factory()->create();
        $colleague = User::factory()->create();
        $user->currentTeam->members()->attach($colleague, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($user->currentTeam)->create();

        Sanctum::actingAs($user, ['read', 'write']);

        $this->postJson(route('api.v1.projects.tasks.store', $project), ['title' => 'Review', 'assignee_id' => $colleague->id])->assertCreated();

        Notification::assertSentTo($colleague, TaskAssigned::class);
    }

    public function test_a_clients_task_waits_for_triage()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $project = Project::factory()->for($team)->create();

        Sanctum::actingAs($client, ['read', 'write']);

        $this->postJson(route('api.v1.projects.tasks.store', $project), ['title' => 'Fix the logo', 'priority' => 'urgent'])
            ->assertCreated()
            ->assertJsonPath('data.needs_triage', true)
            ->assertJsonPath('data.priority', null);
    }

    public function test_a_task_can_be_fetched_by_its_key()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create(['key' => 'WEB']);
        $task = Task::factory()->for($project)->create();

        Sanctum::actingAs($user, ['read', 'write']);

        $this->getJson(route('api.v1.workspaces.tasks.show', [$team, 'web-'.$task->number]))
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);

        $this->getJson(route('api.v1.workspaces.tasks.show', [$team, 'WEB-999']))->assertNotFound();
        $this->getJson(route('api.v1.workspaces.tasks.show', [$team, 'nonsense']))->assertNotFound();
        $this->getJson(route('api.v1.tasks.show', $task))->assertOk()->assertJsonPath('data.key', 'WEB-'.$task->number);
    }

    public function test_only_members_can_see_a_task()
    {
        $task = Task::factory()->create();

        Sanctum::actingAs(User::factory()->create(), ['read', 'write']);

        $this->getJson(route('api.v1.tasks.show', $task))->assertForbidden();
        $this->patchJson(route('api.v1.tasks.update', $task), ['title' => 'Mine now'])->assertForbidden();
        $this->deleteJson(route('api.v1.tasks.destroy', $task))->assertForbidden();
    }

    public function test_one_field_of_a_task_can_be_changed_at_a_time()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create(['title' => 'Original', 'priority' => null]);
        $done = $project->statuses->firstWhere('is_done', true);

        Sanctum::actingAs($user, ['read', 'write']);

        $this->patchJson(route('api.v1.tasks.update', $task), ['priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Original')
            ->assertJsonPath('data.priority', 'low');

        $this->patchJson(route('api.v1.tasks.update', $task), ['status_id' => $done->id])
            ->assertOk()
            ->assertJsonPath('data.status.is_done', true);

        $this->assertNotNull($task->refresh()->completed_at);

        $this->patchJson(route('api.v1.tasks.update', $task), ['start_date' => '2026-12-10', 'due_date' => '2026-12-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_deleting_a_task_moves_it_to_the_trash()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        Sanctum::actingAs($user, ['read', 'write']);

        $this->deleteJson(route('api.v1.tasks.destroy', $task))->assertNoContent();

        $this->assertSoftDeleted($task);
        $this->assertSame($user->id, $task->fresh()->deleted_by);

        $this->getJson(route('api.v1.tasks.show', $task))->assertNotFound();
    }
}
