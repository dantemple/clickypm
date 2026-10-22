<?php

namespace App\Models;

use App\Concerns\SendsLiveUpdates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_internal
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Task $task
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'body', 'is_internal'])]
class Comment extends Model
{
    use SendsLiveUpdates;

    /**
     * Get the task the comment belongs to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the comment's author.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit to comments the given viewer may read: clients never see internal notes.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeVisibleTo(Builder $query, bool $isClient): void
    {
        $query->when($isClient, fn (Builder $query) => $query->where('is_internal', false));
    }

    /**
     * Get the comment as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toCommentData(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
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
            'is_internal' => 'boolean',
        ];
    }

    /**
     * The project (and task) live updates for this model go to.
     *
     * @return array{0: int, 1: int|null}
     */
    public function liveUpdateTarget(): array
    {
        return [(int) $this->task()->withTrashed()->value('project_id'), $this->task_id];
    }
}
