<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_create_a_project_with_default_statuses_and_views()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this
            ->actingAs($user)
            ->post(route('projects.store', $team), ['name' => 'Website', 'color' => '#3b82f6']);

        $project = $team->projects()->firstOrFail();

        $response->assertRedirect(route('projects.views.show', [$team, $project, $project->views->first()]));

        $this->assertSame('Website', $project->name);
        $this->assertSame(['To do', 'In progress', 'Review', 'Done'], $project->statuses->pluck('name')->all());
        $this->assertSame(['list', 'board', 'table'], $project->views->pluck('type.value')->all());
    }

    public function test_project_view_page_includes_tasks_statuses_and_members()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        Task::factory(3)->for($project)->create();

        $this->actingAs($user)
            ->get(route('projects.show', [$team, $project]))
            ->assertRedirect(route('projects.views.show', [$team, $project, $project->views->first()]));

        $this->actingAs($user)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/show')
                ->where('project.id', $project->id)
                ->where('view.type', 'list')
                ->where('view.settings.group_by', 'status')
                ->has('views', 3)
                ->has('statuses', 4)
                ->has('tasks', 3)
                ->has('members', 1),
            );
    }

    public function test_projects_are_shared_with_the_sidebar()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $this->actingAs($user)
            ->get(route('dashboard', $user->currentTeam))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.id', $project->id)
                ->where('projectStats.0.tasksCount', 0),
            );
    }

    public function test_non_members_cannot_see_a_workspaces_projects()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentTeam)->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('projects.show', [$owner->currentTeam, $project]))
            ->assertForbidden();
    }

    public function test_a_project_cannot_be_reached_through_another_workspace()
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.show', [$user->currentTeam, $otherProject]))
            ->assertNotFound();
    }

    public function test_members_can_update_a_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $this->actingAs($user)
            ->patch(route('projects.update', [$user->currentTeam, $project]), [
                'name' => 'Renamed',
                'color' => '#ef4444',
                'description' => 'Now with a description',
            ])
            ->assertRedirect();

        $this->assertSame('Renamed', $project->fresh()->name);
        $this->assertSame('#ef4444', $project->fresh()->color);
    }

    public function test_only_admins_can_delete_a_project()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $project = Project::factory()->for($team)->create();
        Task::factory()->for($project)->create();

        $this->actingAs($member)
            ->delete(route('projects.destroy', [$team, $project]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('projects.destroy', [$team, $project]))
            ->assertRedirect(route('dashboard', $team));

        $this->assertModelMissing($project);
        $this->assertDatabaseCount('tasks', 0);
    }
}
