<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Tasks\CommentMentioned;
use App\Notifications\Tasks\CommentPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommentMentionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $client;

    private Team $team;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->member = User::factory()->create(['name' => 'Sam Member']);
        $this->client = User::factory()->create(['name' => 'Casey Client']);
        $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
        $this->team->members()->attach($this->client, ['role' => TeamRole::Client->value]);
        $this->project = Project::factory()->for($this->team)->create();
        $this->task = Task::factory()->for($this->project)->create(['created_by' => null, 'assignee_id' => null]);
    }

    private function mention(User $user): string
    {
        return "@[{$user->name}](user:{$user->id})";
    }

    private function comment(User $author, string $body, bool $internal = false): Comment
    {
        $this->actingAs($author)
            ->post(route('projects.tasks.comments.store', [$this->team, $this->project, $this->task]), [
                'body' => $body,
                'is_internal' => $internal,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return $this->task->comments()->latest('id')->firstOrFail();
    }

    public function test_mentioned_people_are_recorded_and_emailed()
    {
        $comment = $this->comment($this->owner, 'Over to you '.$this->mention($this->member).' and '.$this->mention($this->client).'.');

        $this->assertEqualsCanonicalizing([$this->member->id, $this->client->id], $comment->mentions()->pluck('users.id')->all());

        Notification::assertSentTo([$this->member, $this->client], CommentMentioned::class, fn (CommentMentioned $notification) => $notification->comment->is($comment));
        Notification::assertNotSentTo([$this->member, $this->client], CommentPosted::class);
        Notification::assertNotSentTo($this->owner, CommentMentioned::class);
    }

    public function test_mentioned_people_hear_about_the_mention_rather_than_the_comment()
    {
        $this->task->update(['assignee_id' => $this->member->id]);

        $this->comment($this->owner, 'Thoughts, '.$this->mention($this->member).'?');

        Notification::assertSentTo($this->member, CommentMentioned::class);
        Notification::assertNotSentTo($this->member, CommentPosted::class);
    }

    public function test_mentions_of_people_who_cannot_see_the_task_are_ignored()
    {
        $outsider = User::factory()->create();
        $this->project->update(['hidden_from_clients' => true]);

        $comment = $this->comment($this->owner, $this->mention($outsider).' '.$this->mention($this->client).' @[Nobody](user:999999)');

        $this->assertSame([], $comment->mentions()->pluck('users.id')->all());
        Notification::assertNothingSent();
    }

    public function test_internal_notes_cannot_mention_clients()
    {
        $comment = $this->comment($this->owner, 'Quietly: '.$this->mention($this->client).' '.$this->mention($this->member), internal: true);

        $this->assertSame([$this->member->id], $comment->mentions()->pluck('users.id')->all());
        Notification::assertSentTo($this->member, CommentMentioned::class);
        Notification::assertNotSentTo($this->client, CommentMentioned::class);
    }

    public function test_mentioning_yourself_sends_nothing()
    {
        $this->comment($this->owner, 'Note to self '.$this->mention($this->owner));

        Notification::assertNothingSent();
    }

    public function test_people_who_turned_task_emails_off_are_not_emailed_about_mentions()
    {
        $this->member->update(['task_emails' => false]);

        $this->comment($this->owner, $this->mention($this->member));

        // They still get the in-app entry, just no email.
        Notification::assertSentTo($this->member, CommentMentioned::class, fn (CommentMentioned $notification, array $channels) => ! in_array('mail', $channels, true));
    }

    public function test_mentioned_people_follow_the_task_from_then_on()
    {
        $this->comment($this->owner, 'Looping in '.$this->mention($this->member));
        $this->comment($this->client, 'Any update?');

        Notification::assertSentTo($this->member, CommentPosted::class);
    }

    public function test_authors_can_edit_their_own_comments()
    {
        $comment = $this->comment($this->member, 'Frist draft');

        $this->actingAs($this->member)
            ->patch(route('projects.tasks.comments.update', [$this->team, $this->project, $this->task, $comment]), ['body' => 'First draft'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $comment->refresh();

        $this->assertSame('First draft', $comment->body);
        $this->assertNotNull($comment->edited_at);

        $this->actingAs($this->member)
            ->get(route('projects.views.show', [$this->team, $this->project, $this->project->views->first(), 'task' => $this->task->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.0.body', 'First draft')
                ->where('comments.0.edited_at', $comment->edited_at->toIso8601String()),
            );
    }

    public function test_only_the_author_can_edit_a_comment()
    {
        $comment = $this->comment($this->member, 'Mine');

        $this->actingAs($this->owner)
            ->patch(route('projects.tasks.comments.update', [$this->team, $this->project, $this->task, $comment]), ['body' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Mine', $comment->refresh()->body);
    }

    public function test_saving_a_comment_unchanged_does_not_mark_it_edited()
    {
        $comment = $this->comment($this->member, 'Same words');

        $this->actingAs($this->member)
            ->patch(route('projects.tasks.comments.update', [$this->team, $this->project, $this->task, $comment]), ['body' => 'Same words'])
            ->assertRedirect();

        $this->assertNull($comment->refresh()->edited_at);
    }

    public function test_editing_only_emails_people_mentioned_for_the_first_time()
    {
        $comment = $this->comment($this->owner, 'Hi '.$this->mention($this->member));

        $this->actingAs($this->owner)
            ->patch(route('projects.tasks.comments.update', [$this->team, $this->project, $this->task, $comment]), [
                'body' => 'Hi '.$this->mention($this->member).' and '.$this->mention($this->client),
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing([$this->member->id, $this->client->id], $comment->mentions()->pluck('users.id')->all());
        Notification::assertSentToTimes($this->member, CommentMentioned::class, 1);
        Notification::assertSentToTimes($this->client, CommentMentioned::class, 1);
        Notification::assertNotSentTo($this->client, CommentPosted::class);
    }

    public function test_editing_a_mention_out_forgets_it()
    {
        $comment = $this->comment($this->owner, 'Hi '.$this->mention($this->member));

        $this->actingAs($this->owner)
            ->patch(route('projects.tasks.comments.update', [$this->team, $this->project, $this->task, $comment]), ['body' => 'Hi everyone'])
            ->assertRedirect();

        $this->assertSame([], $comment->mentions()->pluck('users.id')->all());
    }

    public function test_mention_emails_render_the_name_rather_than_the_markup()
    {
        $comment = $this->comment($this->owner, "Please look, {$this->mention($this->member)}.\nThanks!");

        $mail = (new CommentMentioned($comment))->toMail($this->member);
        $html = (string) $mail->render();

        $this->assertSame('['.$this->project->name.'] '.$this->owner->name.' mentioned you on "'.$this->task->title.'"', $mail->subject);
        $this->assertSame($this->task->url(), $mail->actionUrl);
        $this->assertStringContainsString('@Sam Member', $html);
        $this->assertStringNotContainsString('user:', $html);

        $html = (string) (new CommentPosted($comment))->toMail($this->client)->render();

        $this->assertStringContainsString('@Sam Member', $html);
        $this->assertStringNotContainsString('user:', $html);
    }
}
