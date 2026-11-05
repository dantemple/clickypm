<?php

namespace App\Models;

use App\Concerns\SendsLiveUpdates;
use App\Concerns\Trashable;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $key
 * @property string $color
 * @property string|null $description
 * @property bool $hidden_from_clients
 * @property int|null $github_opened_status_id
 * @property int|null $github_merged_status_id
 * @property int $position
 * @property int|null $created_by
 * @property CarbonImmutable|null $archived_at
 * @property int|null $archived_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $archiver
 * @property-read Collection<int, Status> $statuses
 * @property-read Collection<int, ProjectView> $views
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, CustomField> $customFields
 * @property-read Collection<int, TaskDependency> $dependencies
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, GitHubRepository> $githubRepositories
 * @property-read int|null $tasks_count
 * @property-read int|null $done_tasks_count
 * @property-read int|null $requests_count
 */
#[Fillable(['name', 'key', 'color', 'description', 'hidden_from_clients', 'position', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SendsLiveUpdates, Trashable;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // Every project gets a short key, like WEB, so its tasks can be
        // referred to as WEB-42 from outside the app.
        static::creating(function (Project $project) {
            $project->key = static::uniqueKey($project->key ?: static::suggestKey($project->name), $project->team_id);
        });

        // Trashed projects keep everything; once deleted for good, tasks and
        // attachment rows cascade away in the database and the files go here.
        static::forceDeleted(fn (Project $project) => Storage::disk(config('filesystems.attachments'))
            ->deleteDirectory(Attachment::directoryFor($project->id)));
    }

    /**
     * A key made from the name: the initials of up to four words (Website
     * Relaunch becomes WR), or the first letters of a one-word name.
     */
    public static function suggestKey(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/i', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(implode('', array_map(fn (string $word) => $word[0], array_slice($words, 0, 4))));

        if (strlen($initials) < 2) {
            $initials = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $name) ?? '', 0, 3));
        }

        $key = ltrim($initials, '0123456789');

        return strlen($key) >= 2 ? $key : 'PRJ';
    }

    /**
     * The key, or the first of KEY2, KEY3… that no other project in the
     * workspace already uses.
     */
    public static function uniqueKey(string $key, int $teamId, ?int $ignoreId = null): string
    {
        $taken = static::withTrashed()
            ->where('team_id', $teamId)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('key', 'like', $key.'%')
            ->pluck('key')
            ->all();

        $candidate = $key;

        for ($suffix = 2; in_array($candidate, $taken, true); $suffix++) {
            $candidate = $key.$suffix;
        }

        return $candidate;
    }

    /**
     * Get the team (workspace) the project belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the project's statuses in board order.
     *
     * @return HasMany<Status, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class)->orderBy('position');
    }

    /**
     * Get the project's saved views in tab order.
     *
     * @return HasMany<ProjectView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(ProjectView::class)->orderBy('position');
    }

    /**
     * Get the project's tasks.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the project's tags in name order.
     *
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class)->orderBy('name');
    }

    /**
     * Links between the project's tasks, reached through the waiting task.
     *
     * @return HasManyThrough<TaskDependency, Task, $this>
     */
    public function dependencies(): HasManyThrough
    {
        return $this->hasManyThrough(TaskDependency::class, Task::class, 'project_id', 'successor_id');
    }

    /**
     * The GitHub repositories whose pull requests move this project's tasks.
     *
     * @return BelongsToMany<GitHubRepository, $this>
     */
    public function githubRepositories(): BelongsToMany
    {
        return $this->belongsToMany(GitHubRepository::class, 'github_repository_project', 'project_id', 'github_repository_id')->withTimestamps()->orderBy('full_name');
    }

    /**
     * Get the workspace custom fields the project uses.
     *
     * @return BelongsToMany<CustomField, $this>
     */
    public function customFields(): BelongsToMany
    {
        return $this->belongsToMany(CustomField::class)->withTimestamps()->orderBy('custom_fields.id');
    }

    /**
     * The person who archived the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Projects still in use: the sidebar, dashboard, timeline, search and
     * reminders only look at these.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('projects.archived_at');
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('projects.archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Limit to projects the given viewer may see: clients miss hidden projects.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, bool $isClient): void
    {
        $query->when($isClient, fn (Builder $query) => $query->where('hidden_from_clients', false));
    }

    /**
     * Get the status new tasks land in.
     */
    public function defaultStatus(): Status
    {
        return $this->statuses()->where('is_done', false)->first() ?? $this->statuses()->firstOrFail();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_from_clients' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The project (and task) live updates for this model go to.
     *
     * @return array{0: int, 1: int|null}
     */
    public function liveUpdateTarget(): array
    {
        return [$this->id, null];
    }
}
