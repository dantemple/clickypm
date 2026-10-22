<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property string $title
 * @property int|null $assignee_id
 * @property CarbonImmutable|null $due_date
 * @property int $position
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Task $task
 */
#[Fillable(['title', 'assignee_id', 'due_date', 'position', 'completed_at'])]
class Subtask extends Model
{
    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::created(fn (Subtask $subtask) => $subtask->task->recordActivity('subtask_added', ['title' => $subtask->title]));

        static::updated(function (Subtask $subtask) {
            // Only log ticking and unticking, not edits to an already-done subtask.
            if ((bool) $subtask->completed_at !== (bool) $subtask->getOriginal('completed_at')) {
                $subtask->task->recordActivity(
                    $subtask->completed_at ? 'subtask_completed' : 'subtask_reopened',
                    ['title' => $subtask->title],
                );
            }
        });

        static::deleted(fn (Subtask $subtask) => $subtask->task->recordActivity('subtask_removed', ['title' => $subtask->title]));
    }

    /**
     * Get the task the subtask belongs to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the subtask as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toSubtaskData(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'assignee_id' => $this->assignee_id,
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
