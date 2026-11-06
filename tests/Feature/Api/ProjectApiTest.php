<?php

namespace Tests\Feature\Api;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_are_listed_with_their_statuses_and_tags()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create(['name' => 'Website', 'key' => 'WEB']);
        $project->tags()->create(['name' => 'Design', 'color' => '#ff0000']);
        $archived = Project::factory()->for($team)->create(['archived_at' => now()]);
        Project::factory()->create();

        Sanctum::actingAs($user, ['read', 'write']);

        $this->getJson(route('api.v1.workspaces.projects.index', $team))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.key', 'WEB')
            ->assertJsonPath('data.0.workspace_id', $team->id)
            ->assertJsonCount(4, 'data.0.statuses')
            ->assertJsonPath('data.0.statuses.3.is_done', true)
            ->assertJsonPath('data.0.tags.0.name', 'Design')
            ->assertJsonPath('data.0.url', route('projects.show', [$team, $project]));

        $this->getJson(route('api.v1.workspaces.projects.index', [$team, 'include_archived' => 1]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', $archived->id);
    }

    public function test_clients_do_not_see_projects_hidden_from_them()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $shown = Project::factory()->for($team)->create();
        $hidden = Project::factory()->for($team)->create(['hidden_from_clients' => true]);

        Sanctum::actingAs($client, ['read', 'write']);

        $this->getJson(route('api.v1.workspaces.projects.index', $team))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shown->id);

        $this->getJson(route('api.v1.projects.show', $hidden))->assertForbidden();
        $this->getJson(route('api.v1.projects.show', $shown))->assertOk();
    }

    public function test_a_project_can_be_created_with_a_key()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        Sanctum::actingAs($user, ['read', 'write']);

        $this->postJson(route('api.v1.workspaces.projects.store', $team), [
            'name' => 'Website Relaunch',
            'key' => 'web',
            'color' => '#3b82f6',
            'description' => 'The new site.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Website Relaunch')
            ->assertJsonPath('data.key', 'WEB')
            ->assertJsonPath('data.color', '#3b82f6')
            ->assertJsonCount(4, 'data.statuses');

        $project = $team->projects()->sole();

        $this->assertSame($user->id, $project->created_by);
        $this->assertCount(1, $project->views);

        // Keys are unique per workspace.
        $this->postJson(route('api.v1.workspaces.projects.store', $team), ['name' => 'Another', 'key' => 'WEB'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_clients_and_outsiders_cannot_create_projects()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);

        Sanctum::actingAs($client, ['read', 'write']);
        $this->postJson(route('api.v1.workspaces.projects.store', $team), ['name' => 'Nope'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(), ['read', 'write']);
        $this->postJson(route('api.v1.workspaces.projects.store', $team), ['name' => 'Nope'])->assertForbidden();

        $this->assertCount(0, $team->projects);
    }

    public function test_one_field_of_a_project_can_be_changed_at_a_time()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create(['name' => 'Website', 'key' => 'WEB']);

        Sanctum::actingAs($user, ['read', 'write']);

        $this->patchJson(route('api.v1.projects.update', $project), ['description' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Website')
            ->assertJsonPath('data.description', 'Updated');

        $this->patchJson(route('api.v1.projects.update', $project), ['key' => 'site'])
            ->assertOk()
            ->assertJsonPath('data.key', 'SITE');

        $this->patchJson(route('api.v1.projects.update', $project), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }
}
