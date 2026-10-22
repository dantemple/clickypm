<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Tasks\ClientRequestCreated;
use App\Notifications\Tasks\CommentPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $client;

    private Team $team;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->member = User::factory()->create();
        $this->client = User::factory()->create();
        $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
        $this->team->members()->attach($this->client, ['role' => TeamRole::Client->value]);
        $this->project = Project::factory()->for($this->team)->create();
    }

    private function comment(User $author, Task $task, string $body, bool $internal = false): void
    {
        $this->actingAs($author)
            ->post(route('projects.tasks.comments.store', [$this->team, $this->project, $task]), [
                'body' => $body,
                'is_internal' => $internal,
            ])
            ->assertRedirect();
    }

    public function test_the_team_is_emailed_about_new_client_requests()
    {
        $this->actingAs($this->client)
            ->post(route('projects.tasks.store', [$this->team, $this->project]), ['title' => 'New logo please']);

        Notification::assertSentTo([$this->owner, $this->member], ClientRequestCreated::class);
        Notification::assertNotSentTo($this->client, ClientRequestCreated::class);
    }

    public function test_tasks_created_by_the_team_send_no_request_email()
    {
        $this->actingAs($this->member)
            ->post(route('projects.tasks.store', [$this->team, $this->project]), ['title' => 'Internal chore']);

        Notification::assertNothingSent();
    }

    public function test_client_comments_reach_the_task_followers_and_the_workspace_owner()
    {
        $task = Task::factory()->for($this->project)->create(['created_by' => null, 'assignee_id' => $this->member->id]);

        $this->comment($this->client, $task, 'Any news?');

        Notification::assertSentTo([$this->member, $this->owner], CommentPosted::class);
        Notification::assertNotSentTo($this->client, CommentPosted::class);
    }

    public function test_replies_reach_the_client_who_raised_the_request()
    {
        $request = Task::factory()->for($this->project)->create(['created_by' => $this->client->id, 'needs_triage' => true]);

        $this->comment($this->member, $request, 'We can do that next week.');

        Notification::assertSentTo($this->client, CommentPosted::class);
        Notification::assertNotSentTo($this->member, CommentPosted::class);
    }

    public function test_internal_notes_are_never_emailed_to_clients()
    {
        $task = Task::factory()->for($this->project)->create(['created_by' => $this->client->id, 'assignee_id' => $this->owner->id]);

        $this->comment($this->member, $task, 'Charge extra for this.', internal: true);

        Notification::assertSentTo($this->owner, CommentPosted::class);
        Notification::assertNotSentTo($this->client, CommentPosted::class);
    }

    public function test_clients_are_not_emailed_about_hidden_projects()
    {
        $this->project->update(['hidden_from_clients' => true]);
        $task = Task::factory()->for($this->project)->create(['created_by' => $this->client->id]);

        $this->comment($this->owner, $task, 'Moving this somewhere private.');

        Notification::assertNotSentTo($this->client, CommentPosted::class);
    }

    public function test_emails_link_straight_to_the_task()
    {
        $task = Task::factory()->for($this->project)->create(['title' => 'Fix footer']);

        $mail = (new ClientRequestCreated($task))->toMail($this->owner);

        $this->assertSame('['.$this->project->name.'] New request: Fix footer', $mail->subject);
        $this->assertSame(route('projects.show', [$this->team, $this->project, 'task' => $task->id]), $mail->actionUrl);

        $this->actingAs($this->owner)
            ->get($mail->actionUrl)
            ->assertRedirect(route('projects.views.show', [$this->team, $this->project, $this->project->views->first(), 'task' => $task->id]));
    }

    public function test_comment_emails_render()
    {
        $task = Task::factory()->for($this->project)->create();
        $comment = $task->comments()->create(['user_id' => $this->owner->id, 'body' => "Line one\nLine two", 'is_internal' => true]);

        $html = (string) (new CommentPosted($comment))->toMail($this->member)->render();

        $this->assertStringContainsString('Line two', $html);
        $this->assertStringContainsString('internal note', $html);
    }
}
