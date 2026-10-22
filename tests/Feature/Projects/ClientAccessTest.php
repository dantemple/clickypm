<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Team}
     */
    private function workspaceWithClient(): array
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $client->switchTeam($team);

        return [$owner, $client, $team];
    }

    public function test_clients_see_visible_projects_but_cannot_edit_them()
    {
        [, $client, $team] = $this->workspaceWithClient();
        $project = Project::factory()->for($team)->create();

        $this->actingAs($client)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEdit', false)
                ->where('project.canDelete', false)
                ->where('members.0.email', null),
            );

        $task = Task::factory()->for($project)->create(['title' => 'Original']);

        $this->actingAs($client)
            ->patch(route('projects.tasks.update', [$team, $project, $task]), ['title' => 'Changed'])
            ->assertForbidden();
        $this->actingAs($client)
            ->delete(route('projects.tasks.destroy', [$team, $project, $task]))
            ->assertForbidden();
        $this->actingAs($client)
            ->patch(route('projects.views.update', [$team, $project, $project->views->first()]), ['name' => 'Mine'])
            ->assertForbidden();
        $this->actingAs($client)
            ->post(route('projects.store', $team), ['name' => 'Client project'])
            ->assertForbidden();

        $this->assertSame('Original', $task->fresh()->title);
    }

    public function test_projects_hidden_from_clients_are_invisible_to_them()
    {
        [$owner, $client, $team] = $this->workspaceWithClient();
        $visible = Project::factory()->for($team)->create();
        $hidden = Project::factory()->for($team)->create(['hidden_from_clients' => true]);

        $this->actingAs($client)
            ->get(route('projects.show', [$team, $hidden]))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('dashboard', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $visible->id)
                ->has('projectStats', 1),
            );

        $this->actingAs($owner)
            ->get(route('dashboard', $team))
            ->assertInertia(fn (Assert $page) => $page->has('projects', 2));
    }

    public function test_client_tasks_become_requests_that_staff_triage()
    {
        [$owner, $client, $team] = $this->workspaceWithClient();
        $project = Project::factory()->for($team)->create();
        $done = $project->statuses->firstWhere('is_done', true);

        $this->actingAs($client)
            ->post(route('projects.tasks.store', [$team, $project]), [
                'title' => 'Please add a contact form',
                'description' => 'On the about page',
                'status_id' => $done->id,
                'assignee_id' => $owner->id,
            ])
            ->assertRedirect();

        $request = $project->tasks()->firstOrFail();

        $this->assertTrue($request->needs_triage);
        $this->assertSame($project->defaultStatus()->id, $request->status_id);
        $this->assertNull($request->assignee_id);
        $this->assertSame('On the about page', $request->description);

        $this->actingAs($owner)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tasks', 0)
                ->has('requests', 1)
                ->where('projects.0.requests_count', 1),
            );

        $this->actingAs($client)
            ->post(route('projects.tasks.accept', [$team, $project, $request]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('projects.tasks.accept', [$team, $project, $request]))
            ->assertRedirect();

        $this->assertFalse($request->fresh()->needs_triage);
    }

    public function test_internal_notes_are_hidden_from_clients()
    {
        [$owner, $client, $team] = $this->workspaceWithClient();
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();
        $view = $project->views->first();

        $this->actingAs($owner)
            ->post(route('projects.tasks.comments.store', [$team, $project, $task]), ['body' => 'Internal only', 'is_internal' => true]);
        $this->actingAs($client)
            ->post(route('projects.tasks.comments.store', [$team, $project, $task]), ['body' => 'Sneaky', 'is_internal' => true])
            ->assertRedirect();

        $this->assertFalse(Comment::where('body', 'Sneaky')->value('is_internal'));

        $this->actingAs($client)
            ->get(route('projects.views.show', [$team, $project, $view, 'task' => $task->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('openTaskId', $task->id)
                ->has('comments', 1)
                ->where('comments.0.body', 'Sneaky')
                ->where('tasks.0.comments_count', 1),
            );

        $this->actingAs($owner)
            ->get(route('projects.views.show', [$team, $project, $view, 'task' => $task->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('comments', 2)
                ->where('tasks.0.comments_count', 2),
            );
    }

    public function test_only_the_author_can_delete_a_comment()
    {
        [$owner, $client, $team] = $this->workspaceWithClient();
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();
        $comment = $task->comments()->create(['user_id' => $client->id, 'body' => 'Hello']);

        $this->actingAs($owner)
            ->delete(route('projects.tasks.comments.destroy', [$team, $project, $task, $comment]))
            ->assertForbidden();

        $this->actingAs($client)
            ->delete(route('projects.tasks.comments.destroy', [$team, $project, $task, $comment]))
            ->assertRedirect();

        $this->assertModelMissing($comment);
    }

    public function test_clients_cannot_open_workspace_settings()
    {
        [, $client, $team] = $this->workspaceWithClient();

        $this->actingAs($client)
            ->get(route('teams.edit', $team))
            ->assertForbidden();
    }

    public function test_invited_people_who_sign_up_join_the_workspace_instead_of_getting_their_own()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Client A']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'client@example.com',
            'role' => TeamRole::Client,
            'invited_by' => $owner->id,
        ]);

        $this->post(route('register.store'), [
            'name' => 'Clara Client',
            'email' => 'Client@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', $team));

        $client = User::whereRaw('LOWER(email) = ?', ['client@example.com'])->firstOrFail();

        $this->assertNull($client->personalTeam());
        $this->assertTrue($client->isCurrentTeam($team));
        $this->assertSame(TeamRole::Client, $client->teamRole($team));
    }

    public function test_a_removed_client_with_no_other_workspace_has_no_current_team()
    {
        [$owner, $client, $team] = $this->workspaceWithClient();
        $client->personalTeam()->forceDelete();

        $this->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $client]))
            ->assertRedirect();

        $this->assertNull($client->fresh()->current_team_id);
    }
}
