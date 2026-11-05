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
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_internal
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Task $task
 * @property-read User|null $user
 * @property-read Collection<int, User> $mentions
 */
#[Fillable(['user_id', 'body', 'is_internal', 'edited_at'])]
class Comment extends Model
{
    use SendsLiveUpdates;

    /**
     * How a mention is written in a comment's body: the name people read,
     * and the id that says who it is even if they're renamed later.
     *
     * @example "Over to you @[Dan Temple](user:12)."
     */
    public const string MENTION_PATTERN = '/@\[([^\]\n]{1,255})\]\(user:(\d+)\)/';

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
     * Get the people the comment @-mentions.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_mentions')->withTimestamps();
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
     * The ids of everyone @-mentioned in the given body, in order of first mention.
     *
     * @return array<int, int>
     */
    public static function mentionedIds(string $body): array
    {
        preg_match_all(static::MENTION_PATTERN, $body, $matches);

        return array_values(array_unique(array_map('intval', $matches[2])));
    }

    /**
     * The body as plain text, with each mention written as "@Name".
     */
    public function plainBody(): string
    {
        return (string) preg_replace(static::MENTION_PATTERN, '@$1', $this->body);
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
            'edited_at' => $this->edited_at?->toIso8601String(),
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
            'edited_at' => 'datetime',
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
