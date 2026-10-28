<?php

namespace Tests\Feature\Projects;

use App\Enums\CustomFieldType;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveProjectTest extends TestCase
{
    use RefreshDatabase;

    private function join(Team $team, User $user, TeamRole $role): void
    {
        $team->members()->attach($user, ['role' => $role->value]);
    }

    public function test_owners_can_move_a_project_to_another_workspace_they_belong_to()
    {
        $user = User::factory()->create();
        $personal = $user->currentTeam;
        $client = Team::factory()->create(['name' => 'Client Co']);
        $this->join($client, $user, TeamRole::Admin);
        Project::factory()->for($client)->create(['position' => 4]);
        $project = Project::factory()->for($personal)->create(['name' => 'Website']);
        $task = Task::factory()->for($project)->create(['title' => 'Build it']);

        $this->actingAs($user)
            ->post(route('projects.move', [$personal, $project]), ['team' => $client->slug])
            ->assertRedirect(route('projects.show', [$client, $project]));

        $project->refresh();
        $this->assertTrue($project->team->is($client));
        $this->assertSame(5, $project->position);
        $this->assertTrue($task->fresh()->project->is($project));
        $this->assertCount(0, $personal->projects);

        // Following the redirect lands in the new workspace with the project open.
        $this->actingAs($user)
            ->get(route('projects.show', [$client, $project]))
            ->assertRedirect(route('projects.views.show', [$client, $project, $project->views()->first()]));
        $this->assertTrue($user->fresh()->currentTeam->is($client));
    }

    public function test_assignees_who_are_not_in_the_destination_are_cleared()
    {
        $user = User::factory()->create();
        $colleague = User::factory()->create();
        $outsider = User::factory()->create();
        $source = $user->currentTeam;
        $destination = Team::factory()->create();
        $this->join($source, $colleague, TeamRole::Member);
        $this->join($source, $outsider, TeamRole::Member);
        $this->join($destination, $user, TeamRole::Member);
        $this->join($destination, $colleague, TeamRole::Member);

        $project = Project::factory()->for($source)->create();
        $kept = Task::factory()->for($project)->create(['assignee_id' => $colleague->id]);
        $cleared = Task::factory()->for($project)->create(['assignee_id' => $outsider->id]);
        $subtask = $kept->subtasks()->create(['title' => 'Part', 'position' => 0, 'assignee_id' => $outsider->id]);
        $trashed = Task::factory()->for($project)->create(['assignee_id' => $outsider->id]);
        $trashed->delete();

        $this->actingAs($user)
            ->post(route('projects.move', [$source, $project]), ['team' => $destination->slug])
            ->assertRedirect();

        $this->assertSame($colleague->id, $kept->fresh()->assignee_id);
        $this->assertNull($cleared->fresh()->assignee_id);
        $this->assertNull($subtask->fresh()->assignee_id);
        $this->assertNull(Task::withTrashed()->find($trashed->id)->assignee_id);
    }

    public function test_custom_fields_follow_the_project_reusing_matching_fields_in_the_destination()
    {
        $user = User::factory()->create();
        $source = $user->currentTeam;
        $destination = Team::factory()->create();
        $this->join($destination, $user, TeamRole::Member);

        $budget = $source->customFields()->create(['name' => 'Budget', 'type' => CustomFieldType::Number]);
        $stage = $source->customFields()->create([
            'name' => 'Stage',
            'type' => CustomFieldType::Dropdown,
            'options' => [['name' => 'Lead', 'color' => '#3b82f6']],
        ]);
        $existingBudget = $destination->customFields()->create(['name' => 'Budget', 'type' => CustomFieldType::Number]);

        $project = Project::factory()->for($source)->create();
        $project->customFields()->attach([$budget->id, $stage->id]);
        $task = Task::factory()->for($project)->create();
        $task->customFieldValues()->create(['custom_field_id' => $budget->id, 'value' => 1200]);
        $task->customFieldValues()->create(['custom_field_id' => $stage->id, 'value' => 'Lead']);

        $this->actingAs($user)
            ->post(route('projects.move', [$source, $project]), ['team' => $destination->slug])
            ->assertRedirect();

        $fields = $project->fresh()->customFields;
        $this->assertCount(2, $fields);
        $this->assertTrue($fields->every(fn ($field) => $field->team_id === $destination->id));
        $this->assertTrue($fields->contains($existingBudget));

        $copiedStage = $destination->customFields()->where('name', 'Stage')->sole();
        $this->assertSame(CustomFieldType::Dropdown, $copiedStage->type);
        $this->assertSame('Lead', $copiedStage->options[0]['name']);

        $values = $task->fresh()->customFieldValues->pluck('value', 'custom_field_id');
        $this->assertEquals(1200, $values[$existingBudget->id]);
        $this->assertSame('Lead', $values[$copiedStage->id]);

        // The source workspace keeps its own field definitions.
        $this->assertCount(2, $source->fresh()->customFields);
    }

    public function test_only_workspace_admins_can_move_and_only_into_workspaces_they_can_create_projects_in()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $source = $owner->currentTeam;
        $this->join($source, $member, TeamRole::Member);
        $project = Project::factory()->for($source)->create();

        $asMember = Team::factory()->create();
        $this->join($asMember, $owner, TeamRole::Member);
        $this->join($asMember, $member, TeamRole::Owner);
        $asClient = Team::factory()->create();
        $this->join($asClient, $owner, TeamRole::Client);
        $stranger = Team::factory()->create();

        // Plain members can't move a project out, even into a workspace they own.
        $this->actingAs($member)
            ->post(route('projects.move', [$source, $project]), ['team' => $asMember->slug])
            ->assertForbidden();

        // Admins can't move it somewhere they're only a client, or not a member at all.
        $this->actingAs($owner)
            ->post(route('projects.move', [$source, $project]), ['team' => $asClient->slug])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('projects.move', [$source, $project]), ['team' => $stranger->slug])
            ->assertForbidden();

        // Nor to the workspace it's already in, or one that doesn't exist.
        $this->actingAs($owner)
            ->post(route('projects.move', [$source, $project]), ['team' => $source->slug])
            ->assertForbidden();
        $this->actingAs($owner)
            ->from(route('dashboard', $source))
            ->post(route('projects.move', [$source, $project]), ['team' => 'nowhere'])
            ->assertSessionHasErrors('team');

        $this->assertTrue($project->fresh()->team->is($source));

        // Being a plain member of the destination is enough.
        $this->actingAs($owner)
            ->post(route('projects.move', [$source, $project]), ['team' => $asMember->slug])
            ->assertRedirect(route('projects.show', [$asMember, $project]));
        $this->assertTrue($project->fresh()->team->is($asMember));
    }
}
