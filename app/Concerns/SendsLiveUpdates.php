<?php

namespace App\Concerns;

use App\Support\LiveUpdates;

/**
 * Tells everyone viewing the project when this model is saved, deleted or
 * restored. The model says which project (and task, if any) it belongs to.
 */
trait SendsLiveUpdates
{
    /**
     * Bootstrap the trait.
     */
    public static function bootSendsLiveUpdates(): void
    {
        $notify = fn (self $model) => app(LiveUpdates::class)->projectChanged(...$model->liveUpdateTarget());

        static::saved($notify);
        static::deleted($notify);
        // Only fires for soft-deleting models; harmless on the rest.
        static::registerModelEvent('restored', $notify);
    }

    /**
     * The project id, and task id if any, this model's changes belong to.
     *
     * @return array{0: int, 1: int|null}
     */
    abstract public function liveUpdateTarget(): array;
}
