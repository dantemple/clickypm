<?php

namespace Tests\Feature\GitHub;

use App\Jobs\ProcessGitHubWebhook;
use App\Models\GitHubInstallation;
use App\Models\GitHubLink;
use App\Models\GitHubRepository;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'top-secret';

    private User $owner;

    private Team $team;

    private Project $project;

    private Task $task;

    private GitHubInstallation $installation;

    private GitHubRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.github.app_slug' => 'clickypm', 'services.github.webhook_secret' => self::SECRET]);

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->team->update(['features' => ['github' => true]]);
        $this->project = Project::factory()->for($this->team)->create(['key' => 'WEB']);
        $this->task = Task::factory()->for($this->project)->create(['title' => 'Fix the login button']);

        $this->installation = GitHubInstallation::create(['team_id' => $this->team->id, 'installation_id' => 42, 'account_login' => 'acme', 'account_type' => 'Organization']);
        $this->repository = $this->installation->repositories()->create(['repository_id' => 100, 'full_name' => 'acme/web', 'private' => true]);

        $this->project->githubRepositories()->attach($this->repository);
        $this->project->forceFill([
            'github_opened_status_id' => $this->project->statuses->firstWhere('name', 'Review')->id,
            'github_merged_status_id' => $this->project->statuses->firstWhere('name', 'Done')->id,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(string $event, array $payload, ?string $delivery = null, ?string $secret = self::SECRET): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', route('github.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_GITHUB_DELIVERY' => $delivery ?? fake()->uuid(),
            'HTTP_X_HUB_SIGNATURE_256' => $secret === null ? '' : 'sha256='.hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function pullRequest(string $action, int $number = 7, array $overrides = []): array
    {
        return [
            'action' => $action,
            'repository' => ['id' => 100, 'full_name' => 'acme/web'],
            'pull_request' => [
                'number' => $number,
                'title' => 'Fix login',
                'body' => null,
                'html_url' => "https://github.com/acme/web/pull/{$number}",
                'state' => 'open',
                'merged' => false,
                'draft' => false,
                'user' => ['login' => 'octocat', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
                'head' => ['ref' => 'web-1-fix-login'],
                ...$overrides,
            ],
        ];
    }

    public function test_deliveries_must_be_signed_with_the_webhook_secret()
    {
        $this->deliver('ping', ['zen' => 'Keep it simple.'], secret: 'wrong')->assertForbidden();
        $this->deliver('ping', ['zen' => 'Keep it simple.'], secret: null)->assertForbidden();
        $this->deliver('ping', ['zen' => 'Keep it simple.'])->assertStatus(202);

        config(['services.github.webhook_secret' => null]);

        $this->deliver('ping', ['zen' => 'Keep it simple.'])->assertNotFound();
    }

    public function test_each_delivery_is_queued_once()
    {
        Queue::fake();

        $this->deliver('pull_request', $this->pullRequest('opened'), delivery: 'abc')->assertStatus(202);
        $this->deliver('pull_request', $this->pullRequest('opened'), delivery: 'abc')->assertNoContent();

        Queue::assertPushed(ProcessGitHubWebhook::class, 1);
        Queue::assertPushed(ProcessGitHubWebhook::class, fn (ProcessGitHubWebhook $job) => $job->event === 'pull_request' && $job->payload['pull_request']['number'] === 7);
    }

    public function test_installation_webhooks_keep_the_account_and_repositories_in_step()
    {
        $this->deliver('installation', [
            'action' => 'created',
            'installation' => ['id' => 99, 'account' => ['login' => 'octo-org', 'type' => 'Organization', 'avatar_url' => 'https://example.com/a.png']],
            'repositories' => [['id' => 200, 'full_name' => 'octo-org/api', 'private' => false]],
        ])->assertStatus(202);

        $installation = GitHubInstallation::where('installation_id', 99)->firstOrFail();
        $this->assertNull($installation->team_id);
        $this->assertSame('octo-org', $installation->account_login);
        $this->assertSame(['octo-org/api'], $installation->repositories()->pluck('full_name')->all());

        $this->deliver('installation_repositories', [
            'action' => 'added',
            'installation' => ['id' => 99, 'account' => ['login' => 'octo-org', 'type' => 'Organization']],
            'repositories_added' => [['id' => 201, 'full_name' => 'octo-org/web', 'private' => true]],
            'repositories_removed' => [['id' => 200, 'full_name' => 'octo-org/api']],
        ]);

        $this->assertSame(['octo-org/web'], $installation->repositories()->pluck('full_name')->all());

        $this->deliver('installation', ['action' => 'suspend', 'installation' => ['id' => 99, 'account' => ['login' => 'octo-org']]]);
        $this->assertNotNull($installation->fresh()->suspended_at);

        $this->deliver('installation', ['action' => 'deleted', 'installation' => ['id' => 99, 'account' => ['login' => 'octo-org']]]);
        $this->assertDatabaseMissing('github_installations', ['installation_id' => 99]);
        $this->assertDatabaseMissing('github_repositories', ['repository_id' => 201]);
    }

    public function test_a_pull_request_mentioning_a_task_is_linked_and_moves_it_along()
    {
        $this->deliver('pull_request', $this->pullRequest('opened'))->assertStatus(202);

        $link = $this->task->githubLinks()->firstOrFail();
        $this->assertSame(GitHubLink::PULL_REQUEST, $link->type);
        $this->assertSame('7', $link->external_id);
        $this->assertSame('open', $link->state);
        $this->assertSame('acme/web', $link->repository);
        $this->assertSame('octocat', $link->author);
        $this->assertSame($this->repository->id, $link->github_repository_id);

        $this->assertSame('Review', $this->task->fresh()->status->name);
        $types = $this->task->activities()->pluck('type')->all();
        $this->assertContains('github_pr_linked', $types);
        $this->assertContains('status', $types);
        $this->assertSame('octocat', $this->task->activities()->where('type', 'status')->first()->data['actor']);

        // Edits that keep the same state change nothing.
        $this->deliver('pull_request', $this->pullRequest('edited', overrides: ['title' => 'Fix login button']));
        $this->assertSame('Fix login button', $link->fresh()->title);
        $this->assertCount(1, $this->task->githubLinks);
        $this->assertSame(1, $this->task->activities()->where('type', 'like', 'github_%')->count());

        $this->deliver('pull_request', $this->pullRequest('closed', overrides: ['state' => 'closed', 'merged' => true]));

        $this->assertSame('merged', $link->fresh()->state);
        $this->assertSame('Done', $this->task->fresh()->status->name);
        $this->assertSame('github_pr_merged', $this->task->activities()->where('type', 'like', 'github_%')->pluck('type')->last());
    }

    public function test_tasks_are_found_by_key_in_the_title_and_body_too()
    {
        $other = Task::factory()->for($this->project)->create();

        $this->deliver('pull_request', $this->pullRequest('opened', overrides: [
            'head' => ['ref' => 'feature/no-key-here'],
            'title' => 'WEB-1: tidy up',
            'body' => "Also closes web-{$other->number}.",
        ]));

        $this->assertCount(1, $this->task->githubLinks);
        $this->assertCount(1, $other->githubLinks);
        $this->assertDatabaseMissing('github_links', ['external_id' => '99']);
    }

    public function test_pull_requests_from_other_repositories_link_tasks_but_do_not_move_them()
    {
        $this->installation->repositories()->create(['repository_id' => 101, 'full_name' => 'acme/api']);
        $payload = $this->pullRequest('opened');
        $payload['repository'] = ['id' => 101, 'full_name' => 'acme/api'];

        $this->deliver('pull_request', $payload);

        $this->assertSame('acme/api', $this->task->githubLinks()->firstOrFail()->repository);
        $this->assertSame('To do', $this->task->fresh()->status->name);
    }

    public function test_done_tasks_are_not_pulled_back_by_a_new_pull_request()
    {
        $this->task->update(['status_id' => $this->project->statuses->firstWhere('name', 'Done')->id]);

        $this->deliver('pull_request', $this->pullRequest('opened'));

        $this->assertCount(1, $this->task->githubLinks);
        $this->assertSame('Done', $this->task->fresh()->status->name);
    }

    public function test_nothing_is_linked_while_the_module_is_off_or_the_installation_is_suspended()
    {
        $this->team->update(['features' => ['github' => false]]);
        $this->deliver('pull_request', $this->pullRequest('opened'))->assertStatus(202);
        $this->assertCount(0, $this->task->githubLinks);

        $this->team->update(['features' => ['github' => true]]);
        $this->installation->update(['suspended_at' => now()]);
        $this->deliver('pull_request', $this->pullRequest('opened'));
        $this->assertCount(0, $this->task->githubLinks);

        $this->installation->update(['suspended_at' => null, 'team_id' => null]);
        $this->deliver('pull_request', $this->pullRequest('opened'));
        $this->assertCount(0, $this->task->githubLinks);
    }

    public function test_pushed_commits_mentioning_a_task_are_linked_once()
    {
        $push = [
            'ref' => 'refs/heads/main',
            'repository' => ['id' => 100, 'full_name' => 'acme/web'],
            'commits' => [
                ['id' => 'abcdef1234567890', 'message' => "Fix the login button (WEB-1)\n\nLonger explanation.", 'url' => 'https://github.com/acme/web/commit/abcdef1234567890', 'author' => ['name' => 'Octo Cat', 'username' => 'octocat']],
                ['id' => '1111111111111111', 'message' => 'Unrelated tidy-up', 'url' => 'https://github.com/acme/web/commit/1111111111111111', 'author' => ['name' => 'Octo Cat']],
            ],
        ];

        $this->deliver('push', $push)->assertStatus(202);
        $this->deliver('push', $push);

        $link = $this->task->githubLinks()->firstOrFail();
        $this->assertCount(1, $this->task->githubLinks);
        $this->assertSame(GitHubLink::COMMIT, $link->type);
        $this->assertSame('abcdef1234567890', $link->external_id);
        $this->assertSame('Fix the login button (WEB-1)', $link->title);
        $this->assertSame('octocat', $link->author);
        $this->assertNull($link->state);
        $this->assertSame(1, $this->task->activities()->where('type', 'github_commit')->count());
        $this->assertSame('abcdef1', $this->task->activities()->where('type', 'github_commit')->first()->data['sha']);
        // Commits never move tasks.
        $this->assertSame('To do', $this->task->fresh()->status->name);
    }

    public function test_links_are_shown_on_the_task_while_the_module_is_on()
    {
        $this->deliver('pull_request', $this->pullRequest('opened'));

        $view = $this->project->views->first();

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $view, 'task' => $this->task->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('githubLinks', 1)
                ->where('githubLinks.0.type', 'pull_request')
                ->where('githubLinks.0.external_id', '7')
                ->where('githubLinks.0.state', 'open')
                ->where('githubLinks.0.url', 'https://github.com/acme/web/pull/7'));

        $this->team->update(['features' => ['github' => false]]);

        $this->actingAs($this->owner)
            ->get(route('projects.views.show', [$this->team, $this->project, $view, 'task' => $this->task->id]))
            ->assertInertia(fn (Assert $page) => $page->has('githubLinks', 0)->where('github', null));

        // The link itself is kept for when the module comes back.
        $this->assertDatabaseHas('github_links', ['task_id' => $this->task->id]);
    }

    public function test_links_survive_the_repository_being_disconnected()
    {
        $this->deliver('pull_request', $this->pullRequest('opened'));

        $this->installation->delete();

        $link = GitHubLink::firstOrFail();
        $this->assertNull($link->github_repository_id);
        $this->assertSame('acme/web', $link->repository);
    }
}
