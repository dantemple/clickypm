<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\CommentPosted;
use App\Notifications\Tasks\TaskAssigned;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->owner->currentTeam;
    $this->member = User::factory()->create();
    $this->client = User::factory()->create();
    $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
    $this->team->members()->attach($this->client, ['role' => TeamRole::Client->value]);
    $this->project = Project::factory()->for($this->team)->create();
});

function assignTask(User $actor, Task $task, User $assignee): void
{
    test()->actingAs($actor)
        ->patch(route('projects.tasks.update', [test()->team, test()->project, $task]), ['assignee_id' => $assignee->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
}

function postComment(User $author, Task $task, string $body, bool $internal = false): void
{
    test()->actingAs($author)
        ->post(route('projects.tasks.comments.store', [test()->team, test()->project, $task]), [
            'body' => $body,
            'is_internal' => $internal,
        ])
        ->assertRedirect();
}

test('an assignment writes an inbox entry for the assignee and not the actor', function () {
    $task = Task::factory()->for($this->project)->create(['created_by' => $this->owner->id]);

    assignTask($this->owner, $task, $this->member);

    $notification = $this->member->notifications()->sole();

    expect($notification->type)->toBe(TaskAssigned::class)
        ->and($notification->read_at)->toBeNull()
        ->and($notification->data)->toMatchArray([
            'message' => "{$this->owner->name} assigned you a task",
            'task' => ['id' => $task->id, 'title' => $task->title],
            'project' => ['id' => $this->project->id, 'name' => $this->project->name, 'color' => $this->project->color],
            'actor' => $this->owner->name,
            'url' => $task->url(),
        ]);

    expect($this->owner->notifications()->count())->toBe(0);
});

test('turning task emails off keeps the inbox entry but drops the email', function () {
    Notification::fake();
    $this->member->update(['task_emails' => false]);
    $task = Task::factory()->for($this->project)->create(['created_by' => $this->owner->id]);

    assignTask($this->owner, $task, $this->member);

    Notification::assertSentTo(
        $this->member,
        TaskAssigned::class,
        fn (TaskAssigned $notification, array $channels) => $channels === ['database', 'broadcast'],
    );
});

test('people who want task emails get them alongside the inbox entry', function () {
    Notification::fake();
    $task = Task::factory()->for($this->project)->create(['created_by' => $this->owner->id]);

    assignTask($this->owner, $task, $this->member);

    Notification::assertSentTo(
        $this->member,
        TaskAssigned::class,
        fn (TaskAssigned $notification, array $channels) => $channels === ['database', 'broadcast', 'mail'],
    );
});

test('a client never gets an inbox entry for an internal note', function () {
    $task = Task::factory()->for($this->project)->create(['created_by' => $this->client->id, 'assignee_id' => $this->owner->id]);

    postComment($this->member, $task, 'Charge extra for this.', internal: true);

    expect($this->client->notifications()->count())->toBe(0)
        ->and($this->owner->notifications()->where('type', CommentPosted::class)->count())->toBe(1);
});

test('the inbox lists your own notifications newest first with the unread count shared', function () {
    $older = Task::factory()->for($this->project)->create(['created_by' => $this->owner->id, 'title' => 'Older']);
    $newer = Task::factory()->for($this->project)->create(['created_by' => $this->owner->id, 'title' => 'Newer']);
    $this->travel(-1)->minutes();
    assignTask($this->owner, $older, $this->member);
    $this->travelBack();
    assignTask($this->owner, $newer, $this->member);
    assignTask($this->member, Task::factory()->for($this->project)->create(), $this->owner);

    $this->actingAs($this->member)
        ->getJson(route('notifications.index', $this->team))
        ->assertOk()
        ->assertJsonCount(2, 'notifications')
        ->assertJsonPath('notifications.0.task.title', 'Newer')
        ->assertJsonPath('notifications.1.task.title', 'Older')
        ->assertJsonPath('notifications.0.readAt', null);

    $this->actingAs($this->member)
        ->get(route('dashboard', $this->team))
        ->assertInertia(fn ($page) => $page->where('unreadNotificationsCount', 2));
});

test('opening a notification marks it read and goes to the task', function () {
    $task = Task::factory()->for($this->project)->create(['created_by' => $this->owner->id]);
    assignTask($this->owner, $task, $this->member);
    $notification = $this->member->notifications()->sole();

    $this->actingAs($this->member)
        ->patch(route('notifications.read', [$this->team, $notification->id]))
        ->assertRedirect($task->url());

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('you cannot mark someone else’s notification read', function () {
    $task = Task::factory()->for($this->project)->create(['created_by' => $this->owner->id]);
    assignTask($this->owner, $task, $this->member);
    $notification = $this->member->notifications()->sole();

    $this->actingAs($this->owner)
        ->patch(route('notifications.read', [$this->team, $notification->id]))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all read only touches your own notifications', function () {
    assignTask($this->owner, Task::factory()->for($this->project)->create(['created_by' => $this->owner->id]), $this->member);
    assignTask($this->owner, Task::factory()->for($this->project)->create(['created_by' => $this->owner->id]), $this->member);
    assignTask($this->member, Task::factory()->for($this->project)->create(['created_by' => $this->member->id]), $this->owner);

    $this->actingAs($this->member)
        ->from(route('dashboard', $this->team))
        ->post(route('notifications.read-all', $this->team))
        ->assertRedirect(route('dashboard', $this->team));

    expect($this->member->unreadNotifications()->count())->toBe(0)
        ->and($this->owner->unreadNotifications()->count())->toBe(1);
});
