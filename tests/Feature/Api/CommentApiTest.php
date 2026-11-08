<?php

namespace Tests\Feature\Api;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\CommentMentioned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_are_listed_oldest_first_and_clients_miss_internal_notes()
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $task = Task::factory()->for(Project::factory()->for($team))->create();
        $task->comments()->create(['user_id' => $owner->id, 'body' => 'Hello @[Client](user:'.$client->id.')']);
        $task->comments()->create(['user_id' => $owner->id, 'body' => 'Between us', 'is_internal' => true]);

        Sanctum::actingAs($owner, ['read', 'write']);

        $this->getJson(route('api.v1.tasks.comments.index', $task))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.author.name', 'Owner')
            ->assertJsonPath('data.0.text', 'Hello @Client')
            ->assertJsonPath('data.1.is_internal', true);

        Sanctum::actingAs($client, ['read', 'write']);

        $this->getJson(route('api.v1.tasks.comments.index', $task))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_internal', false);
    }

    public function test_a_comment_can_mention_someone()
    {
        Notification::fake();

        $user = User::factory()->create();
        $colleague = User::factory()->create(['name' => 'Sam']);
        $user->currentTeam->members()->attach($colleague, ['role' => TeamRole::Member->value]);
        $task = Task::factory()->for(Project::factory()->for($user->currentTeam))->create();

        Sanctum::actingAs($user, ['read', 'write']);

        $this->postJson(route('api.v1.tasks.comments.store', $task), ['body' => 'Over to you @[Sam](user:'.$colleague->id.')', 'is_internal' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', true)
            ->assertJsonPath('data.text', 'Over to you @Sam')
            ->assertJsonPath('data.author.id', $user->id);

        $this->assertTrue($task->comments()->sole()->mentions->contains($colleague));

        Notification::assertSentTo($colleague, CommentMentioned::class);
    }

    public function test_clients_cannot_post_internal_notes()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $task = Task::factory()->for(Project::factory()->for($team))->create();

        Sanctum::actingAs($client, ['read', 'write']);

        $this->postJson(route('api.v1.tasks.comments.store', $task), ['body' => 'Looks good', 'is_internal' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', false);

        $this->postJson(route('api.v1.tasks.comments.store', $task), ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }
}
