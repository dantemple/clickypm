<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Deleting moves the model to the workspace's trash. It can be restored for
 * TRASH_DAYS days, after which `model:prune` deletes it for good.
 *
 * @property int|null $deleted_by
 * @property-read User|null $deleter
 */
trait Trashable
{
    use Prunable, SoftDeletes;

    /**
     * How long things stay in the trash before they're deleted for good.
     */
    public const TRASH_DAYS = 30;

    /**
     * Bootstrap the trait.
     */
    public static function bootTrashable(): void
    {
        static::restoring(function (self $model) {
            $model->deleted_by = null;
        });
    }

    /**
     * Move to the trash, remembering who did it.
     */
    public function moveToTrash(User $user): void
    {
        // The soft delete only writes deleted_at, so store the deleter first.
        $this->forceFill(['deleted_by' => $user->id])->saveQuietly();
        $this->delete();
    }

    /**
     * Get the person who moved it to the trash.
     *
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Things that have been in the trash long enough to delete for good.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        // The same as onlyTrashed(), written out so it stays typed as Builder<static>.
        return static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where($this->getQualifiedDeletedAtColumn(), '<=', now()->subDays(self::TRASH_DAYS));
    }
}
