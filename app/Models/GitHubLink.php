<?php

namespace App\Models;

use App\Concerns\SendsLiveUpdates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pull request or commit on GitHub that mentioned the task's key. The
 * repository name is kept on the row so the link outlives a disconnect.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $github_repository_id
 * @property string $repository
 * @property string $type
 * @property string $external_id
 * @property string $title
 * @property string $url
 * @property string|null $state
 * @property string|null $author
 * @property string|null $author_avatar_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Task $task
 * @property-read GitHubRepository|null $githubRepository
 */
#[Fillable(['task_id', 'github_repository_id', 'repository', 'type', 'external_id', 'title', 'url', 'state', 'author', 'author_avatar_url'])]
class GitHubLink extends Model
{
    use SendsLiveUpdates;

    public const PULL_REQUEST = 'pull_request';

    public const COMMIT = 'commit';

    protected $table = 'github_links';

    /**
     * The task the pull request or commit mentioned.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The repository, while it's still connected.
     *
     * @return BelongsTo<GitHubRepository, $this>
     */
    public function githubRepository(): BelongsTo
    {
        return $this->belongsTo(GitHubRepository::class, 'github_repository_id');
    }

    /**
     * The link as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toLinkData(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'repository' => $this->repository,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'url' => $this->url,
            'state' => $this->state,
            'author' => $this->author,
            'author_avatar_url' => $this->author_avatar_url,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The project (and task) live updates for this model go to.
     *
     * @return array{0: int, 1: int|null}
     */
    public function liveUpdateTarget(): array
    {
        return [$this->task->project_id, $this->task_id];
    }
}
