<?php

namespace Tests\Feature\Api;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_describes_the_caller_and_their_workspaces()
    {
        $user = User::factory()->create(['name' => 'Dan']);
        $agency = Team::factory()->create(['name' => 'Agency']);
        $agency->members()->attach($user, ['role' => TeamRole::Member->value]);

        Sanctum::actingAs($user, ['read', 'write']);

        $this->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Dan')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonCount(2, 'data.workspaces')
            ->assertJsonPath('data.workspaces.0.name', 'Agency')
            ->assertJsonPath('data.workspaces.0.role', 'member')
            ->assertJsonPath('data.workspaces.1.slug', $user->currentTeam->slug)
            ->assertJsonPath('data.workspaces.1.role', 'owner')
            ->assertJsonMissingPath('data.password');
    }

    public function test_only_members_can_open_a_workspace()
    {
        $user = User::factory()->create();
        $other = Team::factory()->create();

        Sanctum::actingAs($user, ['read', 'write']);

        $this->getJson(route('api.v1.workspaces.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->currentTeam->id);

        $this->getJson(route('api.v1.workspaces.show', $user->currentTeam))->assertOk()->assertJsonPath('data.is_personal', true);
        $this->getJson(route('api.v1.workspaces.show', $other))->assertForbidden();
        $this->getJson(route('api.v1.workspaces.show', 'no-such-workspace'))->assertNotFound();
    }

    public function test_members_are_listed_with_their_roles_but_not_for_clients()
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $team = $owner->currentTeam;
        $client = User::factory()->create(['name' => 'Client']);
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);

        Sanctum::actingAs($owner, ['read', 'write']);

        $this->getJson(route('api.v1.workspaces.members', $team))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Client')
            ->assertJsonPath('data.0.role', 'client')
            ->assertJsonPath('data.1.email', $owner->email)
            ->assertJsonPath('data.1.role', 'owner');

        Sanctum::actingAs($client, ['read', 'write']);

        $this->getJson(route('api.v1.workspaces.members', $team))->assertForbidden();
    }
}
