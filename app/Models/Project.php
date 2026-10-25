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
 * @property string $color
 * @property string|null $description
 * @property bool $hidden_from_clients
 * @property int $position
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Team $team
 * @property-read Collection<int, Status> $statuses
 * @property-read Collection<int, ProjectView> $views
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, CustomField> $customFields
 * @property-read Collection<int, TaskDependency> $dependencies
 * @property-read int|null $tasks_count
 * @property-read int|null $done_tasks_count
 * @property-read int|null $requests_count
 */
#[Fillable(['name', 'color', 'description', 'hidden_from_clients', 'position', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SendsLiveUpdates, Trashable;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // Trashed projects keep everything; once deleted for good, tasks and
        // attachment rows cascade away in the database and the files go here.
        static::forceDeleted(fn (Project $project) => Storage::disk(config('filesystems.attachments'))
            ->deleteDirectory(Attachment::directoryFor($project->id)));
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
     * Links between the project's tasks, reached through the waiting task.
     *
     * @return HasManyThrough<TaskDependency, Task, $this>
     */
    public function dependencies(): HasManyThrough
    {
        return $this->hasManyThrough(TaskDependency::class, Task::class, 'project_id', 'successor_id');
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
