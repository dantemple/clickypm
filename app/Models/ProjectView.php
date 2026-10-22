<?php

namespace App\Models;

use App\Enums\ViewType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property ViewType $type
 * @property int $position
 * @property array<string, mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['name', 'type', 'position', 'settings'])]
class ProjectView extends Model
{
    /**
     * Get the project the view belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Saved settings layered over the defaults for the view's type.
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>|null>
     */
    protected function settings(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => [
                ...$this->type->defaultSettings(),
                ...($value ? json_decode($value, true) : []),
            ],
            set: fn (?array $value) => $value === null ? null : json_encode($value),
        );
    }

    /**
     * Get the view as an array for the frontend.
     *
     * @return array{id: int, name: string, type: string, settings: array<string, mixed>}
     */
    public function toViewData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'settings' => $this->settings,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => ViewType::class,
        ];
    }
}
