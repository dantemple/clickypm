<?php

namespace App\Models;

use App\Concerns\SendsLiveUpdates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A finish-to-start link between two tasks in the same project: the
 * successor is expected to start once the predecessor is done.
 *
 * @property int $id
 * @property int $predecessor_id
 * @property int $successor_id
 * @property string $type
 * @property int $lag_days
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Task $predecessor
 * @property-read Task $successor
 */
#[Fillable(['predecessor_id', 'successor_id', 'type', 'lag_days'])]
class TaskDependency extends Model
{
    use SendsLiveUpdates;

    public const FINISH_TO_START = 'finish_to_start';

    /**
     * The task that has to finish first.
     *
     * @return BelongsTo<Task, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }

    /**
     * The task that waits.
     *
     * @return BelongsTo<Task, $this>
     */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_id');
    }

    /**
     * Get the link as an array for the frontend.
     *
     * @return array{id: int, predecessor_id: int, successor_id: int, type: string, lag_days: int}
     */
    public function toDependencyData(): array
    {
        return [
            'id' => $this->id,
            'predecessor_id' => $this->predecessor_id,
            'successor_id' => $this->successor_id,
            'type' => $this->type,
            'lag_days' => $this->lag_days,
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
            'lag_days' => 'integer',
        ];
    }

    /**
     * Live updates go to the project both tasks belong to.
     *
     * @return array{0: int, 1: int|null}
     */
    public function liveUpdateTarget(): array
    {
        return [$this->successor->project_id, $this->successor_id];
    }
}
