<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a task's history, e.g. "Dan moved this from To do to Review".
 *
 * Names (statuses, people) are copied into `data` when the activity is
 * recorded, so the history still reads correctly after they change.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $type
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable|null $created_at
 * @property-read Task $task
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'type', 'data'])]
class TaskActivity extends Model
{
    /**
     * Activities are never edited, so there is no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Get the task the activity belongs to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the person who did it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the activity as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toActivityData(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data ?? (object) [],
            'user' => $this->user?->only(['id', 'name']),
            'created_at' => $this->created_at?->toIso8601String(),
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
            'data' => 'array',
        ];
    }
}
