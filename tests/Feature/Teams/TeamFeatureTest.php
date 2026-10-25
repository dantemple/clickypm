<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamFeature;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_are_off_until_switched_on()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->currentTeam->hasFeature(TeamFeature::Gantt));

        $this->actingAs($user)
            ->get(route('teams.edit', $user->currentTeam))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/edit')
                ->where('team.features', [])
                ->where('availableFeatures.0.value', 'gantt'));
    }

    public function test_owners_can_switch_a_module_on_and_off()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->from(route('teams.edit', $team))
            ->patch(route('teams.features.update', $team), ['feature' => 'gantt', 'enabled' => true])
            ->assertRedirect(route('teams.edit', $team));

        $this->assertTrue($team->fresh()->hasFeature(TeamFeature::Gantt));

        // The switch reaches the rest of the app through the shared team props.
        $this->actingAs($user->fresh())
            ->get(route('dashboard', $team))
            ->assertInertia(fn (Assert $page) => $page->where('currentTeam.features.gantt', true));

        $this->actingAs($user)
            ->patch(route('teams.features.update', $team), ['feature' => 'gantt', 'enabled' => false]);

        $this->assertFalse($team->fresh()->hasFeature(TeamFeature::Gantt));
    }

    public function test_unknown_modules_are_rejected()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('teams.features.update', $user->currentTeam), ['feature' => 'time_travel', 'enabled' => true])
            ->assertSessionHasErrors('feature');
    }

    public function test_members_cannot_change_modules()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->patch(route('teams.features.update', $owner->currentTeam), ['feature' => 'gantt', 'enabled' => true])
            ->assertForbidden();
    }

    public function test_gantt_views_need_the_module_switched_on()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();

        $this->actingAs($user)
            ->post(route('projects.views.store', [$team, $project]), ['type' => 'gantt'])
            ->assertSessionHasErrors('type');

        $team->update(['features' => ['gantt' => true]]);

        $this->actingAs($user)
            ->post(route('projects.views.store', [$team, $project]), ['type' => 'gantt'])
            ->assertRedirect();

        $view = $project->views()->get()->last();
        $this->assertSame('Gantt', $view->name);
        $this->assertSame('week', $view->settings['zoom']);

        $this->actingAs($user)
            ->patch(route('projects.views.update', [$team, $project, $view]), ['settings' => ['zoom' => 'month']])
            ->assertSessionHasNoErrors();
        $this->assertSame('month', $view->fresh()->settings['zoom']);

        $this->actingAs($user)
            ->patch(route('projects.views.update', [$team, $project, $view]), ['settings' => ['zoom' => 'decade']])
            ->assertSessionHasErrors('settings.zoom');
    }

    public function test_gantt_views_are_hidden_while_the_module_is_off()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $team->update(['features' => ['gantt' => true]]);

        $this->actingAs($user)->post(route('projects.views.store', [$team, $project]), ['type' => 'gantt']);
        $gantt = $project->views()->get()->last();

        $this->actingAs($user)
            ->get(route('projects.views.show', [$team, $project, $gantt]))
            ->assertInertia(fn (Assert $page) => $page->where('view.type', 'gantt')->has('views', 2));

        $team->update(['features' => ['gantt' => false]]);

        $this->actingAs($user)
            ->get(route('projects.views.show', [$team, $project, $gantt]))
            ->assertRedirect(route('projects.show', [$team, $project]));

        $this->actingAs($user)
            ->get(route('projects.views.show', [$team, $project, $project->views()->first()]))
            ->assertInertia(fn (Assert $page) => $page->has('views', 1));

        // The view is kept for when the module comes back.
        $this->assertDatabaseHas('project_views', ['id' => $gantt->id]);
    }
}
