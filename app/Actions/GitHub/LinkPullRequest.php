<?php

namespace App\Actions\GitHub;

use App\Enums\TeamFeature;
use App\Models\GitHubLink;
use App\Models\GitHubRepository;
use App\Models\Task;
use App\Support\TaskReference;
use Illuminate\Support\Str;

/**
 * Links a pull request to every task its branch, title or body mentions,
 * and moves the tasks of the projects that follow the repository.
 */
class LinkPullRequest
{
    public function __construct(private ResolveTaskReferences $resolve) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $action, array $payload): void
    {
        $repository = GitHubRepository::with('installation.team')->where('repository_id', (int) ($payload['repository']['id'] ?? 0))->first();
        $team = $repository?->installation->team;

        if (! $repository || ! $team || ! $repository->installation->isActive() || ! $team->hasFeature(TeamFeature::GitHub)) {
            return;
        }

        $pull = $payload['pull_request'] ?? [];
        $number = (int) ($pull['number'] ?? 0);

        if ($number === 0) {
            return;
        }

        $references = TaskReference::all(
            (string) ($pull['head']['ref'] ?? ''),
            (string) ($pull['title'] ?? ''),
            (string) ($pull['body'] ?? ''),
        );

        $tasks = $this->resolve->handle($team, $references);

        if ($tasks->isEmpty()) {
            return;
        }

        $state = match (true) {
            (bool) ($pull['merged'] ?? false) => 'merged',
            ($pull['state'] ?? 'open') === 'closed' => 'closed',
            (bool) ($pull['draft'] ?? false) => 'draft',
            default => 'open',
        };

        $author = $pull['user']['login'] ?? null;
        $followers = $repository->projects()->pluck('projects.id')->flip();

        Task::recordingActivityAs($author, function () use ($tasks, $repository, $pull, $number, $state, $author, $followers) {
            foreach ($tasks as $task) {
                $link = GitHubLink::firstOrNew([
                    'task_id' => $task->id,
                    'repository' => $repository->full_name,
                    'type' => GitHubLink::PULL_REQUEST,
                    'external_id' => (string) $number,
                ]);

                $isNew = ! $link->exists;
                $previous = $link->state;

                $link->fill([
                    'github_repository_id' => $repository->id,
                    'title' => Str::limit((string) ($pull['title'] ?? ''), 500, ''),
                    'url' => (string) ($pull['html_url'] ?? ''),
                    'state' => $state,
                    'author' => $author,
                    'author_avatar_url' => $pull['user']['avatar_url'] ?? null,
                ])->save();

                $this->record($task, $link, $isNew, $previous);

                if ($followers->has($task->project_id)) {
                    $this->moveAlong($task, $state, $isNew, $previous);
                }
            }
        });
    }

    /**
     * One history line per thing that happened: linked, merged, closed or reopened.
     */
    private function record(Task $task, GitHubLink $link, bool $isNew, ?string $previous): void
    {
        $type = match (true) {
            $isNew => 'github_pr_linked',
            $link->state === 'merged' && $previous !== 'merged' => 'github_pr_merged',
            $link->state === 'closed' && $previous !== 'closed' => 'github_pr_closed',
            in_array($link->state, ['open', 'draft'], true) && in_array($previous, ['closed'], true) => 'github_pr_reopened',
            default => null,
        };

        if ($type === null) {
            return;
        }

        $task->recordActivity($type, [
            'number' => (int) $link->external_id,
            'title' => $link->title,
            'url' => $link->url,
            'repository' => $link->repository,
            'state' => $link->state,
        ]);
    }

    /**
     * Apply the project's automation: an open pull request pulls the task
     * into one status (unless it's already done), a merge pushes it to another.
     */
    private function moveAlong(Task $task, string $state, bool $isNew, ?string $previous): void
    {
        $project = $task->project;

        $opened = in_array($state, ['open', 'draft'], true) && ($isNew || $previous === 'closed');
        $merged = $state === 'merged' && $previous !== 'merged';

        $status = match (true) {
            $merged => $project->github_merged_status_id,
            $opened && $task->completed_at === null => $project->github_opened_status_id,
            default => null,
        };

        if ($status !== null && $status !== $task->status_id && $project->statuses()->whereKey($status)->exists()) {
            $task->update(['status_id' => $status]);
        }
    }
}
