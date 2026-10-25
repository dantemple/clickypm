<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $client;

    private Team $team;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->client = User::factory()->create();
        $this->team->members()->attach($this->client, ['role' => TeamRole::Client->value]);
        $this->project = Project::factory()->for($this->team)->create(['name' => 'Website relaunch']);
    }

    private function search(User $user, string $term): TestResponse
    {
        return $this->actingAs($user)
            ->getJson(route('search', [$this->team, 'q' => $term]))
            ->assertOk();
    }

    /**
     * @return array<int, int>
     */
    private function ids(TestResponse $response, string $group): array
    {
        return array_column($response->json($group), 'id');
    }

    public function test_it_needs_at_least_two_characters()
    {
        Task::factory()->for($this->project)->create(['title' => 'A task']);

        $this->search($this->owner, 'a')
            ->assertExactJson(['projects' => [], 'tasks' => [], 'comments' => []]);
    }

    public function test_it_finds_projects_tasks_and_comments_in_the_workspace()
    {
        $byTitle = Task::factory()->for($this->project)->create(['title' => 'Fix the footer', 'description' => null, 'updated_at' => now()->subDay()]);
        $byDescription = Task::factory()->for($this->project)->create(['title' => 'Homepage polish', 'description' => 'The footer overlaps the content.']);
        Task::factory()->for($this->project)->create(['title' => 'Unrelated', 'description' => null]);
        $comment = $byDescription->comments()->create(['user_id' => $this->owner->id, 'body' => 'Is the footer fixed on mobile yet?']);
        $byDescription->comments()->create(['user_id' => $this->owner->id, 'body' => 'Nothing to see here.']);

        $footerProject = Project::factory()->for($this->team)->create(['name' => 'Footer redesign']);

        $elsewhere = Project::factory()->create(['name' => 'Footer elsewhere']);
        Task::factory()->for($elsewhere)->create(['title' => 'Footer in another workspace']);

        $response = $this->search($this->owner, 'FOOTER');

        $this->assertSame([$footerProject->id], $this->ids($response, 'projects'));
        $this->assertSame([$byTitle->id, $byDescription->id], $this->ids($response, 'tasks'));
        $this->assertSame([$comment->id], $this->ids($response, 'comments'));

        $response->assertJsonPath('tasks.0.project.name', 'Website relaunch')
            ->assertJsonPath('tasks.0.url', route('projects.show', [$this->team, $this->project, 'task' => $byTitle->id]))
            ->assertJsonPath('comments.0.task.title', 'Homepage polish')
            ->assertJsonPath('comments.0.url', route('projects.show', [$this->team, $this->project, 'task' => $byDescription->id]));

        $this->assertStringContainsString('footer', $response->json('comments.0.snippet'));
    }

    public function test_like_wildcards_in_the_search_are_taken_literally()
    {
        $percent = Task::factory()->for($this->project)->create(['title' => '100% done']);
        Task::factory()->for($this->project)->create(['title' => 'Plain task', 'description' => null]);

        $this->assertSame([$percent->id], $this->ids($this->search($this->owner, '0% d'), 'tasks'));
        $this->assertSame([], $this->ids($this->search($this->owner, '%%'), 'tasks'));
        $this->assertSame([], $this->ids($this->search($this->owner, '__'), 'tasks'));
    }

    public function test_things_in_the_trash_are_left_out()
    {
        Task::factory()->for($this->project)->create(['title' => 'Binned task'])->moveToTrash($this->owner);

        $binnedProject = Project::factory()->for($this->team)->create(['name' => 'Binned project']);
        $task = Task::factory()->for($binnedProject)->create(['title' => 'Binned with project']);
        $task->comments()->create(['user_id' => $this->owner->id, 'body' => 'Binned comment']);
        $binnedProject->moveToTrash($this->owner);

        $this->search($this->owner, 'binned')
            ->assertExactJson(['projects' => [], 'tasks' => [], 'comments' => []]);
    }

    public function test_clients_only_see_what_they_could_open()
    {
        $hidden = Project::factory()->for($this->team)->create(['name' => 'Secret budget', 'hidden_from_clients' => true]);
        Task::factory()->for($hidden)->create(['title' => 'Secret pricing']);

        $task = Task::factory()->for($this->project)->create(['title' => 'Secret sauce page']);
        $task->comments()->create(['user_id' => $this->owner->id, 'body' => 'Secret: client is overpaying', 'is_internal' => true]);
        $public = $task->comments()->create(['user_id' => $this->owner->id, 'body' => 'Secret sauce copy is ready']);

        $mine = Task::factory()->for($this->project)->create(['title' => 'Secret request of mine', 'needs_triage' => true, 'created_by' => $this->client->id]);
        Task::factory()->for($this->project)->create(['title' => 'Secret request of theirs', 'needs_triage' => true, 'created_by' => $this->owner->id]);

        $response = $this->search($this->client, 'secret');

        $this->assertSame([], $this->ids($response, 'projects'));
        $this->assertEqualsCanonicalizing([$task->id, $mine->id], $this->ids($response, 'tasks'));
        $this->assertSame([$public->id], $this->ids($response, 'comments'));

        $this->assertCount(4, $this->search($this->owner, 'secret')->json('tasks'));
    }

    public function test_only_members_can_search_a_workspace()
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('search', [$this->team, 'q' => 'anything']))
            ->assertForbidden();
    }
}
