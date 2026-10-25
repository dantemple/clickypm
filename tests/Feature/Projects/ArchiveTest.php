<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\DueTasksReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_archive_and_unarchive_a_project()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();

        $this->actingAs($user)
            ->post(route('projects.archive', [$team, $project]))
            ->assertRedirect(route('dashboard', $team));

        $project->refresh();
        $this->assertTrue($project->isArchived());
        $this->assertSame($user->id, $project->archived_by);

        $this->actingAs($user)
            ->post(route('projects.unarchive', [$team, $project]))
            ->assertRedirect();

        $this->assertFalse($project->fresh()->isArchived());
        $this->assertNull($project->fresh()->archived_by);
    }

    public function test_clients_cannot_archive()
    {
        $owner = User::factory()->create();
        $client = User::factory()->create();
        $owner->currentTeam->members()->attach($client, ['role' => TeamRole::Client->value]);
        $project = Project::factory()->for($owner->currentTeam)->create();

        $this->actingAs($client)
            ->post(route('projects.archive', [$owner->currentTeam, $project]))
            ->assertForbidden();
    }

    public function test_archived_projects_leave_the_sidebar_dashboard_timeline_and_search()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->update(['features' => ['gantt' => true]]);
        $live = Project::factory()->for($team)->create(['name' => 'Live rollout']);
        $old = Project::factory()->for($team)->create(['name' => 'Old rollout']);
        Task::factory()->for($live)->create(['title' => 'Rollout step', 'assignee_id' => $user->id, 'due_date' => '2026-11-01']);
        Task::factory()->for($old)->create(['title' => 'Rollout retro', 'assignee_id' => $user->id, 'due_date' => '2026-11-02']);

        $this->actingAs($user)->post(route('projects.archive', [$team, $old]));

        $this->actingAs($user)
            ->get(route('dashboard', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.name', 'Live rollout')
                ->where('archivedProjectsCount', 1)
                ->has('projectStats', 1)
                ->has('myTasks', 1)
                ->where('myTasks.0.title', 'Rollout step'));

        $this->actingAs($user)
            ->get(route('timeline', $team))
            ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->has('tasks', 1));

        $this->actingAs($user)
            ->getJson(route('search', [$team, 'q' => 'rollout']))
            ->assertJsonCount(1, 'projects')
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Rollout step');
    }

    public function test_archived_projects_send_no_reminders()
    {
        Notification::fake();
        $this->travelTo('2026-11-02 09:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        Task::factory()->for($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-01']);

        $this->actingAs($user)->post(route('projects.archive', [$user->currentTeam, $project]));
        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        Notification::assertNotSentTo($user, DueTasksReminder::class);
    }

    public function test_archived_projects_are_listed_and_still_open()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create(['name' => 'Done and dusted']);
        Task::factory()->count(3)->for($project)->create();

        $this->actingAs($user)->post(route('projects.archive', [$team, $project]));

        $this->actingAs($user)
            ->get(route('archived', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/archived')
                ->has('archivedProjects', 1)
                ->where('archivedProjects.0.name', 'Done and dusted')
                ->where('archivedProjects.0.tasksCount', 3)
                ->where('archivedProjects.0.archivedBy', $user->name)
                ->where('canUnarchive', true));

        $this->actingAs($user)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.name', 'Done and dusted')
                ->whereNot('project.archived_at', null));
    }

    public function test_clients_see_the_archived_list_without_hidden_projects_or_buttons()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $shown = Project::factory()->for($team)->create();
        $hidden = Project::factory()->for($team)->create(['hidden_from_clients' => true]);
        $this->actingAs($owner)->post(route('projects.archive', [$team, $shown]));
        $this->actingAs($owner)->post(route('projects.archive', [$team, $hidden]));

        $this->actingAs($client)
            ->get(route('archived', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->has('archivedProjects', 1)
                ->where('archivedProjects.0.id', $shown->id)
                ->where('canUnarchive', false)
                ->where('archivedProjectsCount', 1));
    }
}
