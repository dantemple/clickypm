<?php

namespace App\Actions\GitHub;

use App\Enums\TeamFeature;
use App\Models\GitHubLink;
use App\Models\GitHubRepository;
use App\Models\Task;
use App\Support\TaskReference;
use Illuminate\Support\Str;

/**
 * Links each pushed commit to the tasks its message mentions.
 */
class LinkCommits
{
    public function __construct(private ResolveTaskReferences $resolve) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $repository = GitHubRepository::with('installation.team')->where('repository_id', (int) ($payload['repository']['id'] ?? 0))->first();
        $team = $repository?->installation->team;

        if (! $repository || ! $team || ! $repository->installation->isActive() || ! $team->hasFeature(TeamFeature::GitHub)) {
            return;
        }

        foreach ($payload['commits'] ?? [] as $commit) {
            $sha = (string) ($commit['id'] ?? '');
            $message = (string) ($commit['message'] ?? '');

            if ($sha === '' || ($tasks = $this->resolve->handle($team, TaskReference::all($message)))->isEmpty()) {
                continue;
            }

            $author = $commit['author']['username'] ?? $commit['author']['name'] ?? null;
            $title = Str::limit(Str::before($message, "\n"), 500, '');

            Task::recordingActivityAs($author, function () use ($tasks, $repository, $sha, $title, $commit, $author) {
                foreach ($tasks as $task) {
                    $link = GitHubLink::firstOrNew([
                        'task_id' => $task->id,
                        'repository' => $repository->full_name,
                        'type' => GitHubLink::COMMIT,
                        'external_id' => $sha,
                    ]);

                    if ($link->exists) {
                        continue;
                    }

                    $link->fill([
                        'github_repository_id' => $repository->id,
                        'title' => $title,
                        'url' => (string) ($commit['url'] ?? $repository->url()."/commit/{$sha}"),
                        'author' => $author,
                    ])->save();

                    $task->recordActivity('github_commit', [
                        'sha' => substr($sha, 0, 7),
                        'title' => $title,
                        'url' => $link->url,
                        'repository' => $repository->full_name,
                    ]);
                }
            });
        }
    }
}
