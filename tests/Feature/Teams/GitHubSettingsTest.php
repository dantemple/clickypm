<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\GitHubInstallation;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GitHubSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.github.app_slug' => 'clickypm', 'services.github.webhook_secret' => 'top-secret']);

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->team->update(['features' => ['github' => true]]);
    }

    public function test_the_module_is_offered_and_the_settings_show_whether_it_is_connected()
    {
        $this->actingAs($this->owner)
            ->get(route('teams.edit', $this->team))
            ->assertInertia(fn (Assert $page) => $page
                ->where('availableFeatures.1.value', 'github')
                ->where('github.configured', true)
                ->where('github.installation', null));

        $installation = GitHubInstallation::create(['team_id' => $this->team->id, 'installation_id' => 42, 'account_login' => 'acme', 'account_type' => 'Organization']);
        $installation->repositories()->create(['repository_id' => 100, 'full_name' => 'acme/web', 'private' => true]);

        $this->actingAs($this->owner)
            ->get(route('teams.edit', $this->team))
            ->assertInertia(fn (Assert $page) => $page
                ->where('github.installation.account_login', 'acme')
                ->where('github.installation.manage_url', 'https://github.com/organizations/acme/settings/installations/42')
                ->where('github.installation.repositories.0.full_name', 'acme/web'));

        config(['services.github.app_slug' => null]);

        $this->actingAs($this->owner)
            ->get(route('teams.edit', $this->team))
            ->assertInertia(fn (Assert $page) => $page->where('github.configured', false));
    }

    public function test_connecting_sends_owners_to_github_with_a_state()
    {
        $response = $this->actingAs($this->owner)->get(route('teams.github.connect', $this->team));

        $state = session('github.setup.state');
        $this->assertNotEmpty($state);
        $this->assertSame($this->team->id, session('github.setup.team_id'));
        $response->assertRedirect('https://github.com/apps/clickypm/installations/new?state='.$state);

        $member = User::factory()->create();
        $this->team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $this->actingAs($member)->get(route('teams.github.connect', $this->team))->assertForbidden();

        $this->team->update(['features' => []]);
        $this->actingAs($this->owner)->get(route('teams.github.connect', $this->team))->assertNotFound();
    }

    public function test_the_setup_callback_claims_the_installation_for_the_workspace()
    {
        // The webhook usually gets there first and knows the account.
        GitHubInstallation::create(['installation_id' => 42, 'account_login' => 'acme', 'account_type' => 'Organization']);

        $this->actingAs($this->owner)
            ->withSession(['github.setup' => ['state' => 'abc', 'team_id' => $this->team->id]])
            ->get(route('github.setup', ['installation_id' => 42, 'setup_action' => 'install', 'state' => 'abc']))
            ->assertRedirect(route('teams.edit', $this->team));

        $this->assertSame($this->team->id, GitHubInstallation::where('installation_id', 42)->value('team_id'));

        // Or the person lands here first, and the row is made to be filled in later.
        $this->actingAs($this->owner)
            ->withSession(['github.setup' => ['state' => 'def', 'team_id' => $this->team->id]])
            ->get(route('github.setup', ['installation_id' => 43, 'setup_action' => 'install', 'state' => 'def']))
            ->assertRedirect(route('teams.edit', $this->team));

        $this->assertDatabaseHas('github_installations', ['installation_id' => 43, 'team_id' => $this->team->id]);
        // A workspace has one installation; the earlier one is replaced.
        $this->assertDatabaseMissing('github_installations', ['installation_id' => 42]);
    }

    public function test_the_setup_callback_rejects_bad_states_and_other_workspaces_installations()
    {
        $this->actingAs($this->owner)
            ->withSession(['github.setup' => ['state' => 'abc', 'team_id' => $this->team->id]])
            ->get(route('github.setup', ['installation_id' => 42, 'state' => 'nope']))
            ->assertRedirect(route('teams.index'));

        $this->assertDatabaseMissing('github_installations', ['installation_id' => 42]);

        $other = Team::factory()->create();
        GitHubInstallation::create(['team_id' => $other->id, 'installation_id' => 42, 'account_login' => 'acme']);

        $this->actingAs($this->owner)
            ->withSession(['github.setup' => ['state' => 'abc', 'team_id' => $this->team->id]])
            ->get(route('github.setup', ['installation_id' => 42, 'state' => 'abc']))
            ->assertRedirect(route('teams.edit', $this->team));

        $this->assertSame($other->id, GitHubInstallation::where('installation_id', 42)->value('team_id'));
    }

    public function test_disconnecting_forgets_the_installation()
    {
        GitHubInstallation::create(['team_id' => $this->team->id, 'installation_id' => 42, 'account_login' => 'acme']);

        $this->actingAs($this->owner)
            ->from(route('teams.edit', $this->team))
            ->delete(route('teams.github.destroy', $this->team))
            ->assertRedirect(route('teams.edit', $this->team));

        $this->assertDatabaseMissing('github_installations', ['installation_id' => 42]);
    }

    public function test_projects_choose_the_repositories_they_follow_and_where_tasks_go()
    {
        $installation = GitHubInstallation::create(['team_id' => $this->team->id, 'installation_id' => 42, 'account_login' => 'acme']);
        $web = $installation->repositories()->create(['repository_id' => 100, 'full_name' => 'acme/web']);
        $api = $installation->repositories()->create(['repository_id' => 101, 'full_name' => 'acme/api']);
        $foreign = GitHubInstallation::create(['installation_id' => 7, 'account_login' => 'someone'])->repositories()->create(['repository_id' => 900, 'full_name' => 'someone/else']);

        $project = Project::factory()->for($this->team)->create();
        $otherProject = Project::factory()->for($this->team)->create();
        $review = $project->statuses->firstWhere('name', 'Review');
        $done = $project->statuses->firstWhere('name', 'Done');

        $this->actingAs($this->owner)
            ->put(route('projects.github.update', [$this->team, $project]), [
                'repository_ids' => [$web->id, $api->id],
                'opened_status_id' => $review->id,
                'merged_status_id' => $done->id,
            ])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(['acme/api', 'acme/web'], $project->githubRepositories()->pluck('full_name')->all());
        $this->assertSame($review->id, $project->github_opened_status_id);
        $this->assertSame($done->id, $project->github_merged_status_id);

        $this->actingAs($this->owner)
            ->put(route('projects.github.update', [$this->team, $project]), ['repository_ids' => [$foreign->id]])
            ->assertSessionHasErrors('repository_ids.0');

        $this->actingAs($this->owner)
            ->put(route('projects.github.update', [$this->team, $project]), [
                'repository_ids' => [],
                'merged_status_id' => $otherProject->statuses->first()->id,
            ])
            ->assertSessionHasErrors('merged_status_id');

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('github.connected', true)
                ->has('github.repositories', 2)
                ->where('github.repository_ids', [$api->id, $web->id])
                ->where('github.opened_status_id', $review->id));

        $this->team->update(['features' => []]);

        $this->actingAs($this->owner)
            ->put(route('projects.github.update', [$this->team, $project]), ['repository_ids' => []])
            ->assertNotFound();
    }

    public function test_clients_do_not_get_the_project_github_settings()
    {
        $client = User::factory()->create();
        $this->team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $project = Project::factory()->for($this->team)->create();

        $this->actingAs($client)
            ->get(route('projects.views.show', [$this->team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page->where('github', null));
    }
}
