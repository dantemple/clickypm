<?php

namespace Tests\Feature\Projects;

use App\Enums\CustomFieldType;
use App\Enums\TeamRole;
use App\Models\CustomField;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    private function field(Team $team, CustomFieldType $type, array $attributes = []): CustomField
    {
        return $team->customFields()->create([
            'name' => ucfirst($type->value).' field',
            'type' => $type,
            ...$attributes,
        ]);
    }

    private function member(Team $team, TeamRole $role): User
    {
        $user = User::factory()->create();
        $team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($team);

        return $user;
    }

    public function test_workspace_admins_can_add_edit_and_delete_fields()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $this->actingAs($owner)
            ->post(route('teams.fields.store', $team), [
                'name' => 'Stage',
                'type' => 'dropdown',
                'options' => [
                    ['name' => 'Lead', 'color' => '#3b82f6'],
                    ['name' => 'Won', 'color' => '#22c55e'],
                ],
            ])
            ->assertRedirect();

        $field = $team->customFields()->sole();
        $this->assertSame(CustomFieldType::Dropdown, $field->type);
        $this->assertSame(['Lead', 'Won'], array_column($field->options, 'name'));

        $this->actingAs($owner)
            ->patch(route('teams.fields.update', [$team, $field]), [
                'name' => 'Deal stage',
                'hidden_from_clients' => true,
                'options' => [
                    ['id' => $field->options[0]['id'], 'name' => 'Prospect', 'color' => '#3b82f6'],
                ],
            ])
            ->assertRedirect();

        $field->refresh();
        $this->assertSame('Deal stage', $field->name);
        $this->assertTrue($field->hidden_from_clients);
        $this->assertSame([['id' => $field->options[0]['id'], 'name' => 'Prospect', 'color' => '#3b82f6']], $field->options);

        $this->actingAs($owner)
            ->delete(route('teams.fields.destroy', [$team, $field]))
            ->assertRedirect();

        $this->assertModelMissing($field);
    }

    public function test_field_definitions_are_validated()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $existing = $this->field($team, CustomFieldType::Text, ['name' => 'Budget']);

        $this->actingAs($owner)
            ->post(route('teams.fields.store', $team), ['name' => 'Budget', 'type' => 'formula'])
            ->assertSessionHasErrors(['name', 'type']);

        $this->actingAs($owner)
            ->post(route('teams.fields.store', $team), ['name' => 'Stage', 'type' => 'dropdown'])
            ->assertSessionHasErrors('options');

        $this->actingAs($owner)
            ->post(route('teams.fields.store', $team), ['name' => 'Notes', 'type' => 'text', 'options' => [['name' => 'A', 'color' => '#000000']]])
            ->assertSessionHasErrors('options');

        $this->actingAs($owner)
            ->patch(route('teams.fields.update', [$team, $existing]), ['type' => 'number'])
            ->assertSessionHasErrors('type');
    }

    public function test_only_workspace_admins_can_manage_fields()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $field = $this->field($team, CustomFieldType::Text);
        $member = $this->member($team, TeamRole::Member);

        $this->actingAs($member)
            ->post(route('teams.fields.store', $team), ['name' => 'Mine', 'type' => 'text'])
            ->assertForbidden();
        $this->actingAs($member)
            ->delete(route('teams.fields.destroy', [$team, $field]))
            ->assertForbidden();

        $other = User::factory()->create();
        $this->actingAs($other)
            ->delete(route('teams.fields.destroy', [$other->currentTeam, $field]))
            ->assertNotFound();

        $this->assertModelExists($field);
    }

    public function test_projects_choose_which_workspace_fields_they_use()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $member = $this->member($team, TeamRole::Member);
        $project = Project::factory()->for($team)->create();
        $budget = $this->field($team, CustomFieldType::Number, ['name' => 'Budget']);
        $this->field($team, CustomFieldType::Text, ['name' => 'Unused']);
        $elsewhere = $this->field(User::factory()->create()->currentTeam, CustomFieldType::Text);

        $this->actingAs($member)
            ->put(route('projects.fields.update', [$team, $project]), ['custom_field_ids' => [$elsewhere->id]])
            ->assertSessionHasErrors('custom_field_ids.0');

        $this->actingAs($member)
            ->put(route('projects.fields.update', [$team, $project]), ['custom_field_ids' => [$budget->id]])
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customFields', 1)
                ->where('customFields.0.name', 'Budget')
                ->has('workspaceFields', 2)
                ->where('canManageFields', false),
            );
    }

    public function test_tasks_store_validated_values_and_record_them_in_history()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();
        $stage = $this->field($team, CustomFieldType::Dropdown, ['name' => 'Stage', 'options' => [['id' => 'won', 'name' => 'Won', 'color' => '#22c55e']]]);
        $budget = $this->field($team, CustomFieldType::Number, ['name' => 'Budget']);
        $signed = $this->field($team, CustomFieldType::Checkbox, ['name' => 'Signed']);
        $project->customFields()->attach([$stage->id, $budget->id, $signed->id]);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$team, $project, $task]), [
                'custom_fields' => [$stage->id => 'lost', $budget->id => 'lots'],
            ])
            ->assertSessionHasErrors(["custom_fields.{$stage->id}", "custom_fields.{$budget->id}"]);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$team, $project, $task]), [
                'custom_fields' => [$stage->id => 'won', $budget->id => '1250.5', $signed->id => true],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$stage->id => 'won', $budget->id => 1250.5, $signed->id => true],
            $task->customFieldValues()->pluck('value', 'custom_field_id')->all(),
        );

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$team, $project, $task]), [
                'custom_fields' => [$budget->id => null, $signed->id => false],
            ])
            ->assertRedirect();

        $this->assertSame([$stage->id], $task->customFieldValues()->pluck('custom_field_id')->all());

        $history = $task->activities()->where('type', 'custom_field')->pluck('data')->all();
        $this->assertCount(5, $history);
        $this->assertSame(['field_id' => $stage->id, 'name' => 'Stage', 'type' => 'dropdown', 'from' => null, 'to' => 'Won'], $history[0]);
        $this->assertSame(['field_id' => $budget->id, 'name' => 'Budget', 'type' => 'number', 'from' => '1250.5', 'to' => null], $history[3]);
    }

    public function test_values_can_be_set_when_creating_a_task()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $due = $this->field($team, CustomFieldType::Date, ['name' => 'Launch']);
        $project->customFields()->attach($due);

        $this->actingAs($user)
            ->post(route('projects.tasks.store', [$team, $project]), [
                'title' => 'Ship it',
                'custom_fields' => [$due->id => '2026-12-01'],
            ])
            ->assertRedirect();

        $this->assertSame('2026-12-01', $project->tasks()->sole()->customFieldValues()->sole()->value);
    }

    public function test_fields_the_project_does_not_use_are_rejected()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();
        $used = $this->field($team, CustomFieldType::Text);
        $unused = $this->field($team, CustomFieldType::Url);
        $project->customFields()->attach($used);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$team, $project, $task]), [
                'custom_fields' => [$unused->id => 'https://example.com'],
            ])
            ->assertSessionHasErrors('custom_fields');

        $this->assertSame(0, $task->customFieldValues()->count());
    }

    public function test_removing_a_dropdown_option_clears_tasks_that_chose_it()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $project = Project::factory()->for($team)->create();
        $field = $this->field($team, CustomFieldType::Dropdown, ['options' => [
            ['id' => 'keep', 'name' => 'Keep', 'color' => '#000000'],
            ['id' => 'drop', 'name' => 'Drop', 'color' => '#ffffff'],
        ]]);
        $project->customFields()->attach($field);
        $kept = Task::factory()->for($project)->create();
        $dropped = Task::factory()->for($project)->create();
        $kept->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'keep']);
        $dropped->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'drop']);

        $this->actingAs($owner)
            ->patch(route('teams.fields.update', [$team, $field]), [
                'options' => [['id' => 'keep', 'name' => 'Kept', 'color' => '#000000']],
            ])
            ->assertRedirect();

        $this->assertSame(1, $kept->customFieldValues()->count());
        $this->assertSame(0, $dropped->customFieldValues()->count());
    }

    public function test_clients_do_not_see_hidden_fields_or_their_values()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = $this->member($team, TeamRole::Client);
        $project = Project::factory()->for($team)->create();
        $public = $this->field($team, CustomFieldType::Text, ['name' => 'Phase']);
        $internal = $this->field($team, CustomFieldType::Number, ['name' => 'Margin', 'hidden_from_clients' => true]);
        $project->customFields()->attach([$public->id, $internal->id]);
        $task = Task::factory()->for($project)->create();
        $task->customFieldValues()->create(['custom_field_id' => $public->id, 'value' => 'Design']);
        $task->customFieldValues()->create(['custom_field_id' => $internal->id, 'value' => 40]);

        $this->actingAs($client)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customFields', 1)
                ->where('customFields.0.name', 'Phase')
                ->where('tasks.0.custom_fields', [$public->id => 'Design'])
                ->where('workspaceFields', []),
            );

        $this->actingAs($owner)
            ->get(route('projects.views.show', [$team, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customFields', 2)
                ->where('tasks.0.custom_fields', [$public->id => 'Design', $internal->id => 40]),
            );

        $this->actingAs($client)
            ->patch(route('projects.tasks.update', [$team, $project, $task]), ['custom_fields' => [$public->id => 'Build']])
            ->assertForbidden();
    }

    public function test_clients_do_not_see_history_for_hidden_fields()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = $this->member($team, TeamRole::Client);
        $project = Project::factory()->for($team)->create();
        $public = $this->field($team, CustomFieldType::Text, ['name' => 'Phase']);
        $internal = $this->field($team, CustomFieldType::Number, ['name' => 'Margin', 'hidden_from_clients' => true]);
        $project->customFields()->attach([$public->id, $internal->id]);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($owner)->patch(route('projects.tasks.update', [$team, $project, $task]), [
            'custom_fields' => [$public->id => 'Design', $internal->id => 40],
        ]);

        $page = fn (User $user) => $this->actingAs($user)
            ->get(route('projects.views.show', [$team, $project, $project->views->first(), 'task' => $task->id]));

        $page($client)->assertInertia(fn (Assert $page) => $page
            ->where('activity', fn ($activity) => collect($activity)->where('type', 'custom_field')->pluck('data.name')->all() === ['Phase']),
        );
        $page($owner)->assertInertia(fn (Assert $page) => $page
            ->where('activity', fn ($activity) => collect($activity)->where('type', 'custom_field')->pluck('data.name')->all() === ['Phase', 'Margin']),
        );
    }

    public function test_table_views_can_show_and_sort_by_the_projects_fields()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        $field = $this->field($team, CustomFieldType::Number);
        $unused = $this->field($team, CustomFieldType::Text);
        $project->customFields()->attach($field);
        $view = $project->views->first();

        $this->actingAs($user)
            ->patch(route('projects.views.update', [$team, $project, $view]), [
                'settings' => ['columns' => ["field_{$unused->id}"]],
            ])
            ->assertSessionHasErrors('settings.columns.0');

        $this->actingAs($user)
            ->patch(route('projects.views.update', [$team, $project, $view]), [
                'settings' => [
                    'columns' => ['status', "field_{$field->id}"],
                    'sort' => ['column' => "field_{$field->id}", 'direction' => 'desc'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['status', "field_{$field->id}"], $view->refresh()->settings['columns']);
    }

    public function test_the_workspace_settings_page_lists_fields_with_how_many_projects_use_them()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $field = $this->field($team, CustomFieldType::Text, ['name' => 'Client']);
        Project::factory()->for($team)->count(2)->create()->each(fn (Project $project) => $project->customFields()->attach($field));

        $this->actingAs($owner)
            ->get(route('teams.edit', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customFields.0.name', 'Client')
                ->where('customFields.0.projects_count', 2)
                ->has('fieldTypes', count(CustomFieldType::cases())),
            );
    }
}
