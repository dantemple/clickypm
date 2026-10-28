<?php

namespace App\Models;

use App\Concerns\SendsLiveUpdates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label a project's tasks can carry, any number at a time. Tags are made
 * up on the spot from a task and shared across the project.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $color
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read Collection<int, Task> $tasks
 * @property-read int|null $tasks_count
 */
#[Fillable(['name', 'color'])]
class Tag extends Model
{
    use SendsLiveUpdates;

    /**
     * How many tags a project can have.
     */
    public const MAX_PER_PROJECT = 50;

    /**
     * How long a tag's name can be.
     */
    public const MAX_NAME_LENGTH = 30;

    /**
     * Colours new tags cycle through; the same palette projects pick from.
     *
     * @var array<int, string>
     */
    public const COLORS = [
        '#7c3aed',
        '#3b82f6',
        '#0ea5e9',
        '#14b8a6',
        '#22c55e',
        '#eab308',
        '#f97316',
        '#ef4444',
        '#ec4899',
        '#64748b',
    ];

    /**
     * Get the project the tag belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tasks carrying the tag.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)->withTimestamps();
    }

    /**
     * Only tags called the given name, whatever its case.
     *
     * @param  Builder<Tag>  $query
     */
    public function scopeNamed(Builder $query, string $name): void
    {
        $query->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))]);
    }

    /**
     * The colour the next new tag in a project gets.
     */
    public static function nextColor(Project $project): string
    {
        return self::COLORS[$project->tags()->count() % count(self::COLORS)];
    }

    /**
     * Get the tag as an array for the frontend.
     *
     * @return array{id: int, name: string, color: string, tasks_count?: int}
     */
    public function toTagData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            // Only where the caller counted them (the project's tag list).
            ...($this->tasks_count !== null ? ['tasks_count' => $this->tasks_count] : []),
        ];
    }

    /**
     * The project (and task) live updates for this model go to.
     *
     * @return array{0: int, 1: int|null}
     */
    public function liveUpdateTarget(): array
    {
        return [$this->project_id, null];
    }
}
