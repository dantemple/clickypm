<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Events\ProjectChanged;
use App\Events\WorkspaceChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\LiveUpdates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LiveUpdatesTest extends TestCase
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

        // Setup saves aren't part of any request; drop them before each test.
        app(LiveUpdates::class)->flush();
    }

    public function test_a_change_sends_one_project_and_one_workspace_message()
    {
        $task = Task::factory()->for($this->project)->create();
        Event::fake([ProjectChanged::class, WorkspaceChanged::class]);

        $this->actingAs($this->owner)
            ->withHeader('X-Socket-ID', '1234.5678')
            ->patch(route('projects.tasks.update', [$this->team, $this->project, $task]), ['title' => 'Renamed'])
            ->assertRedirect();

        Event::assertDispatchedTimes(ProjectChanged::class, 1);
        Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->projectId === $this->project->id
            && $event->taskIds === [$task->id]
            && $event->socket === '1234.5678'
            && $event->broadcastOn()[0]->name === "private-projects.{$this->project->id}");
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event) => $event->teamId === $this->team->id
            && $event->socket === '1234.5678');
    }

    public function test_a_board_move_touching_several_tasks_is_still_one_message()
    {
        $todo = $this->project->statuses->first();
        [$a, $b, $c] = Task::factory(3)->for($this->project)
            ->sequence(['position' => 1], ['position' => 2], ['position' => 3])
            ->create(['status_id' => $todo->id]);
        Event::fake([ProjectChanged::class, WorkspaceChanged::class]);

        $this->actingAs($this->owner)->post(route('projects.tasks.move', [$this->team, $this->project, $c]), [
            'status_id' => $todo->id,
            'order' => [$c->id, $a->id, $b->id],
        ]);

        Event::assertDispatchedTimes(ProjectChanged::class, 1);
        Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => collect($event->taskIds)->sort()->values()->all() === collect([$a->id, $b->id, $c->id])->sort()->values()->all());
    }

    public function test_messages_never_carry_content()
    {
        $task = Task::factory()->for($this->project)->create();
        Event::fake([ProjectChanged::class]);

        $this->actingAs($this->owner)->post(route('projects.tasks.comments.store', [$this->team, $this->project, $task]), [
            'body' => 'Charge them double',
            'is_internal' => true,
        ]);

        Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->broadcastWith() === ['taskIds' => [$task->id]]);
    }

    public function test_project_level_changes_are_announced()
    {
        Event::fake([ProjectChanged::class, WorkspaceChanged::class]);

        $this->actingAs($this->owner)->patch(route('projects.update', [$this->team, $this->project]), ['name' => 'Renamed']);
        $this->actingAs($this->owner)->delete(route('projects.destroy', [$this->team, $this->project]));

        Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->taskIds === []);
        Event::assertDispatchedTimes(WorkspaceChanged::class, 2);
    }

    public function test_nothing_is_sent_when_nothing_changed()
    {
        Event::fake([ProjectChanged::class, WorkspaceChanged::class]);

        $this->actingAs($this->owner)->get(route('projects.show', [$this->team, $this->project]));

        Event::assertNotDispatched(ProjectChanged::class);
        Event::assertNotDispatched(WorkspaceChanged::class);
    }

    public function test_changes_made_in_queued_jobs_are_sent_when_the_job_finishes()
    {
        $task = Task::factory()->for($this->project)->create();
        app(LiveUpdates::class)->flush();
        Event::fake([ProjectChanged::class]);

        dispatch(fn () => $task->update(['title' => 'From a job']));

        Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->taskIds === [$task->id]);
    }

    public function test_channels_follow_project_and_workspace_access()
    {
        $this->useReverbForChannelAuth();

        $client = User::factory()->create();
        $this->team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $outsider = User::factory()->create();
        $hidden = Project::factory()->for($this->team)->create(['hidden_from_clients' => true]);

        $this->authorise($this->owner, "projects.{$hidden->id}")->assertOk();
        $this->authorise($client, "projects.{$this->project->id}")->assertOk();
        $this->authorise($client, "projects.{$hidden->id}")->assertForbidden();
        $this->authorise($outsider, "projects.{$this->project->id}")->assertForbidden();

        $this->authorise($client, "workspaces.{$this->team->id}")->assertOk();
        $this->authorise($outsider, "workspaces.{$this->team->id}")->assertForbidden();
    }

    /**
     * Tests use the null broadcaster, which approves every channel. Switch to
     * Reverb and register the app's channels on it so their rules apply.
     */
    private function useReverbForChannelAuth(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

    private function authorise(User $user, string $channel): TestResponse
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-{$channel}",
        ]);
    }
}
