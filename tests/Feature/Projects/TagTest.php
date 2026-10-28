<?php

namespace Tests\Feature\Projects;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tags_are_made_in_the_project_and_added_to_the_task()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['new_tags' => ['Bug', ' urgent ']])
            ->assertRedirect();

        $this->assertSame(['Bug', 'urgent'], $task->tags()->pluck('name')->all());
        $this->assertSame(['Bug', 'urgent'], $project->tags()->pluck('name')->all());
        $this->assertSame([Tag::COLORS[0], Tag::COLORS[1]], $project->tags()->pluck('color')->all());
        $this->assertSame(['tagged', 'tagged'], $task->activities()->where('type', 'tagged')->pluck('type')->all());
    }

    public function test_an_existing_tag_of_the_same_name_is_reused_whatever_its_case()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $bug = $project->tags()->create(['name' => 'Bug', 'color' => '#ef4444']);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['new_tags' => ['bug']])
            ->assertRedirect();

        $this->assertCount(1, $project->tags);
        $this->assertTrue($task->tags()->sole()->is($bug));
    }

    public function test_tag_ids_replace_the_tasks_tags()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        [$bug, $urgent, $design] = collect(['Bug', 'Urgent', 'Design'])
            ->map(fn ($name) => $project->tags()->create(['name' => $name, 'color' => '#ef4444']));
        $task = Task::factory()->for($project)->create();
        $task->tags()->attach([$bug->id, $urgent->id]);

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['tag_ids' => [$urgent->id, $design->id]])
            ->assertRedirect();

        $this->assertSame(['Design', 'Urgent'], $task->tags()->pluck('name')->all());

        $history = $task->activities()->whereIn('type', ['tagged', 'untagged'])->get();
        $this->assertSame('tagged', $history->firstWhere('data.name', 'Design')->type);
        $this->assertSame('untagged', $history->firstWhere('data.name', 'Bug')->type);
        $this->assertNull($history->firstWhere('data.name', 'Urgent'));
    }

    public function test_tags_can_be_set_when_a_task_is_created()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $bug = $project->tags()->create(['name' => 'Bug', 'color' => '#ef4444']);

        $this->actingAs($user)
            ->post(route('projects.tasks.store', [$user->currentTeam, $project]), [
                'title' => 'Fix login',
                'tag_ids' => [$bug->id],
                'new_tags' => ['Auth'],
            ])
            ->assertRedirect();

        $this->assertSame(['Auth', 'Bug'], $project->tasks()->sole()->tags()->pluck('name')->all());
    }

    public function test_tags_from_other_projects_and_bad_names_are_rejected()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $other = Project::factory()->for($user->currentTeam)->create();
        $foreign = $other->tags()->create(['name' => 'Elsewhere', 'color' => '#ef4444']);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['tag_ids' => [$foreign->id]])
            ->assertSessionHasErrors('tag_ids.0');

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['new_tags' => [str_repeat('x', 31)]])
            ->assertSessionHasErrors('new_tags.0');

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['new_tags' => ['Same', 'same']])
            ->assertSessionHasErrors('new_tags.0');

        $this->assertCount(0, $task->tags);
    }

    public function test_a_project_has_a_limit_on_tags()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $task = Task::factory()->for($project)->create();

        for ($i = 0; $i < Tag::MAX_PER_PROJECT; $i++) {
            $project->tags()->create(['name' => "Tag {$i}", 'color' => '#ef4444']);
        }

        $this->actingAs($user)
            ->patch(route('projects.tasks.update', [$user->currentTeam, $project, $task]), ['new_tags' => ['One more']])
            ->assertSessionHasErrors('new_tags');

        $this->assertCount(Tag::MAX_PER_PROJECT, $project->tags);
    }

    public function test_the_projects_tag_list_can_be_edited()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $bug = $project->tags()->create(['name' => 'Bug', 'color' => '#ef4444']);
        $old = $project->tags()->create(['name' => 'Old', 'color' => '#64748b']);
        $task = Task::factory()->for($project)->create();
        $task->tags()->attach([$bug->id, $old->id]);

        $this->actingAs($user)
            ->put(route('projects.tags.update', [$user->currentTeam, $project]), [
                'tags' => [
                    ['id' => $bug->id, 'name' => 'Defect', 'color' => '#f97316'],
                    ['id' => null, 'name' => 'Idea', 'color' => '#eab308'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(['Defect', 'Idea'], $project->tags()->pluck('name')->all());
        $this->assertSame('#f97316', $bug->fresh()->color);
        $this->assertModelMissing($old);
        $this->assertSame(['Defect'], $task->tags()->pluck('name')->all());

        $this->actingAs($user)
            ->put(route('projects.tags.update', [$user->currentTeam, $project]), [
                'tags' => [
                    ['id' => $bug->id, 'name' => 'Dup', 'color' => '#f97316'],
                    ['id' => null, 'name' => 'dup', 'color' => '#eab308'],
                ],
            ])
            ->assertSessionHasErrors('tags.0.name');
    }

    public function test_tags_are_sent_with_the_projects_tasks()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $bug = $project->tags()->create(['name' => 'Bug', 'color' => '#ef4444']);
        $task = Task::factory()->for($project)->create();
        $task->tags()->attach($bug);

        $this->actingAs($user)
            ->get(route('projects.views.show', [$user->currentTeam, $project, $project->views->first()]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tags', 1)
                ->where('tags.0.name', 'Bug')
                ->where('tasks.0.tags.0.id', $bug->id)
                ->where('tasks.0.tags.0.color', '#ef4444'),
            );
    }

    public function test_clients_cannot_tag_tasks_or_edit_the_tag_list()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $client = User::factory()->create();
        $team->members()->attach($client, ['role' => TeamRole::Client->value]);
        $client->switchTeam($team);
        $project = Project::factory()->for($team)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($client)
            ->patch(route('projects.tasks.update', [$team, $project, $task]), ['new_tags' => ['Bug']])
            ->assertForbidden();

        $this->actingAs($client)
            ->put(route('projects.tags.update', [$team, $project]), ['tags' => []])
            ->assertForbidden();

        // A client's request is created without any tags they might send.
        $this->actingAs($client)
            ->post(route('projects.tasks.store', [$team, $project]), ['title' => 'Please', 'new_tags' => ['Bug']])
            ->assertRedirect();

        $this->assertCount(0, $project->tags);
    }

    public function test_tags_go_with_a_deleted_task_and_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $bug = $project->tags()->create(['name' => 'Bug', 'color' => '#ef4444']);
        $task = Task::factory()->for($project)->create();
        $task->tags()->attach($bug);

        // Trashing keeps the tag on the task so restoring brings it back.
        $task->delete();
        $this->assertCount(1, Task::withTrashed()->find($task->id)->tags);

        $task->forceDelete();
        $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id]);
        $this->assertModelExists($bug);

        $project->forceDelete();
        $this->assertModelMissing($bug);
    }
}
