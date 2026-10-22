<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $color
 * @property int $position
 * @property bool $is_done
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['name', 'color', 'position', 'is_done'])]
class Status extends Model
{
    /**
     * The statuses every new project starts with.
     *
     * @var array<array{name: string, color: string, is_done: bool}>
     */
    public const DEFAULTS = [
        ['name' => 'To do', 'color' => '#87909e', 'is_done' => false],
        ['name' => 'In progress', 'color' => '#3b82f6', 'is_done' => false],
        ['name' => 'Review', 'color' => '#f59e0b', 'is_done' => false],
        ['name' => 'Done', 'color' => '#22c55e', 'is_done' => true],
    ];

    /**
     * Get the project the status belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tasks currently in this status.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }
}
