<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewAndStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_views_can_be_added_renamed_and_configured()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();

        $this->actingAs($user)
            ->post(route('projects.views.store', [$team, $project]), ['type' => 'table'])
            ->assertRedirect();

        $view = $project->views()->get()->last();
        $this->assertSame('Table', $view->name);

        $this->actingAs($user)
            ->patch(route('projects.views.update', [$team, $project, $view]), [
                'name' => 'Due soon',
                'settings' => [
                    'sort' => ['column' => 'due_date', 'direction' => 'asc'],
                    'columns' => ['status', 'due_date'],
                ],
            ])
            ->assertRedirect();

        $view->refresh();

        $this->assertSame('Due soon', $view->name);
        $this->assertSame(['column' => 'due_date', 'direction' => 'asc'], $view->settings['sort']);
        $this->assertSame(['status', 'due_date'], $view->settings['columns']);
        $this->assertTrue($view->settings['show_closed']);
    }

    public function test_invalid_view_settings_are_rejected()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();

        $this->actingAs($user)
            ->patch(route('projects.views.update', [$user->currentTeam, $project, $project->views->first()]), [
                'settings' => ['group_by' => 'colour', 'columns' => ['password']],
            ])
            ->assertSessionHasErrors(['settings.group_by', 'settings.columns.0']);
    }

    public function test_the_last_view_cannot_be_deleted()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $project = Project::factory()->for($team)->create();
        [$list, $board, $table] = $project->views;

        $this->actingAs($user)->delete(route('projects.views.destroy', [$team, $project, $board]));

        $this->actingAs($user)
            ->delete(route('projects.views.destroy', [$team, $project, $table]))
            ->assertRedirect(route('projects.views.show', [$team, $project, $list]));

        $this->actingAs($user)->delete(route('projects.views.destroy', [$team, $project, $list]));

        $this->assertModelExists($list);
    }

    public function test_statuses_can_be_renamed_reordered_added_and_removed()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        [$todo, $progress, $review, $done] = $project->statuses;
        $reviewTask = Task::factory()->for($project)->create(['status_id' => $review->id]);

        $this->actingAs($user)
            ->put(route('projects.statuses.update', [$user->currentTeam, $project]), [
                'statuses' => [
                    ['id' => $progress->id, 'name' => 'Doing', 'color' => '#3b82f6', 'is_done' => false],
                    ['id' => $todo->id, 'name' => 'Backlog', 'color' => '#87909e', 'is_done' => false],
                    ['id' => null, 'name' => 'Blocked', 'color' => '#ef4444', 'is_done' => false],
                    ['id' => $done->id, 'name' => 'Done', 'color' => '#22c55e', 'is_done' => true],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(['Doing', 'Backlog', 'Blocked', 'Done'], $project->statuses()->pluck('name')->all());
        $this->assertModelMissing($review);
        $this->assertSame($progress->id, $reviewTask->fresh()->status_id);
    }

    public function test_flipping_a_status_to_done_completes_its_tasks()
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentTeam)->create();
        $statuses = $project->statuses;
        $task = Task::factory()->for($project)->create(['status_id' => $statuses[2]->id]);

        $this->actingAs($user)
            ->put(route('projects.statuses.update', [$user->currentTeam, $project]), [
                'statuses' => $statuses->map(fn ($status, $index) => [
                    ...$status->only(['id', 'name', 'color']),
                    'is_done' => $index >= 2,
                ])->all(),
            ]);

        $this->assertNotNull($task->fresh()->completed_at);
    }
}
