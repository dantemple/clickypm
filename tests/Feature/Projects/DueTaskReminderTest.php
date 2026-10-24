<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Tasks\DueTasksReminder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DueTaskReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Team $team;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo('2026-10-24 09:00:00');

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->member = User::factory()->create();
        $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
        $this->project = Project::factory()->for($this->team)->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function task(?string $dueDate, array $attributes = []): Task
    {
        return Task::factory()->for($this->project)->create([
            'assignee_id' => $this->member->id,
            'due_date' => $dueDate,
            ...$attributes,
        ]);
    }

    public function test_assignees_get_one_email_listing_their_overdue_and_due_today_tasks()
    {
        $lastWeek = $this->task('2026-10-17');
        $yesterday = $this->task('2026-10-23');
        $today = $this->task('2026-10-24');
        $this->task('2026-10-25');
        $this->task(null);
        $this->task('2026-10-20', ['assignee_id' => null]);
        $this->task('2026-10-20', ['needs_triage' => true]);
        $this->task('2026-10-20', ['status_id' => $this->project->statuses()->where('is_done', true)->firstOrFail()->id]);

        $this->artisan('tasks:send-due-reminders')
            ->expectsOutputToContain('1 reminder sent.')
            ->assertSuccessful();

        Notification::assertSentToTimes($this->member, DueTasksReminder::class, 1);
        Notification::assertSentTo($this->member, DueTasksReminder::class, function (DueTasksReminder $reminder) use ($lastWeek, $yesterday, $today) {
            $this->assertSame([$lastWeek->id, $yesterday->id, $today->id], $reminder->tasks->modelKeys());

            return true;
        });
        Notification::assertNotSentTo($this->owner, DueTasksReminder::class);
    }

    public function test_nothing_is_sent_when_nobody_has_anything_due()
    {
        $this->task('2026-10-30');

        $this->artisan('tasks:send-due-reminders')
            ->expectsOutputToContain('0 reminders sent.')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_tasks_in_the_trash_are_left_out()
    {
        $this->task('2026-10-20')->moveToTrash($this->owner);

        $trashedProject = Project::factory()->for($this->team)->create();
        Task::factory()->for($trashedProject)->create(['assignee_id' => $this->member->id, 'due_date' => '2026-10-20']);
        $trashedProject->moveToTrash($this->owner);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_people_who_turned_reminders_off_are_not_emailed()
    {
        $this->member->update(['reminder_emails' => false]);
        $this->task('2026-10-20');

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_people_no_longer_in_the_workspace_are_not_emailed()
    {
        $this->task('2026-10-20');
        $this->team->members()->detach($this->member);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_reminder_is_scheduled_for_each_morning()
    {
        // Scheduled commands run in their own process, so check the schedule itself.
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'tasks:send-due-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertTrue($event->onOneServer);
    }

    public function test_reminder_emails_render()
    {
        $overdue = $this->task('2026-10-22', ['title' => 'Chase the invoice']);
        $today = $this->task('2026-10-24', ['title' => 'Ship the footer']);

        $tasks = Task::whereKey([$overdue->id, $today->id])->with('project.team')->orderBy('due_date')->get();
        $mail = (new DueTasksReminder($tasks))->toMail($this->member);
        $html = (string) $mail->render();

        $this->assertSame('Your tasks: 1 task overdue, 1 task due today', $mail->subject);
        $this->assertSame(route('dashboard', $this->team), $mail->actionUrl);
        $this->assertStringContainsString('Chase the invoice', $html);
        $this->assertStringContainsString('due 2 days ago', $html);
        $this->assertStringContainsString('Ship the footer', $html);
        $this->assertStringContainsString('due today', $html);
        $this->assertStringContainsString($overdue->url(), $html);
    }
}
