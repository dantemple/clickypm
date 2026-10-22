<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * A field defined once for the workspace, which each project can choose to
 * use. Tasks in those projects then hold a value for it.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property CustomFieldType $type
 * @property array<int, array{id: string, name: string, color: string}>|null $options
 * @property bool $hidden_from_clients
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Project> $projects
 * @property-read int|null $projects_count
 * @property-read Collection<int, CustomFieldValue> $values
 */
#[Fillable(['name', 'type', 'options', 'hidden_from_clients'])]
class CustomField extends Model
{
    /**
     * How many fields a workspace can define.
     */
    public const MAX_PER_TEAM = 50;

    /**
     * Get the workspace the field belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the projects that use the field.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /**
     * Get the values tasks hold for the field.
     *
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * Limit to fields the given viewer may see: clients miss hidden fields.
     *
     * @param  Builder<CustomField>  $query
     */
    public function scopeVisibleTo(Builder $query, bool $isClient): void
    {
        $query->when($isClient, fn (Builder $query) => $query->where('hidden_from_clients', false));
    }

    /**
     * Validation rules for a value of this field. Empty values clear it.
     *
     * @return array<int, mixed>
     */
    public function valueRules(): array
    {
        return match ($this->type) {
            CustomFieldType::Text => ['nullable', 'string', 'max:2000'],
            CustomFieldType::Number => ['nullable', 'numeric', 'min:-999999999999', 'max:999999999999'],
            CustomFieldType::Date => ['nullable', 'date_format:Y-m-d'],
            CustomFieldType::Dropdown => ['nullable', 'string', Rule::in(array_column($this->options ?? [], 'id'))],
            CustomFieldType::Checkbox => ['nullable', 'boolean'],
            CustomFieldType::Url => ['nullable', 'url:http,https', 'max:2000'],
        };
    }

    /**
     * Turn a validated value into what gets stored, or null to clear it.
     */
    public function normalize(mixed $value): string|int|float|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this->type) {
            CustomFieldType::Text, CustomFieldType::Url => trim((string) $value) ?: null,
            CustomFieldType::Number => $value + 0,
            CustomFieldType::Date, CustomFieldType::Dropdown => (string) $value,
            // Unticked is the same as never set.
            CustomFieldType::Checkbox => filter_var($value, FILTER_VALIDATE_BOOLEAN) ?: null,
        };
    }

    /**
     * Describe a stored value for the task's history.
     */
    public function describe(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            CustomFieldType::Dropdown => collect($this->options)->firstWhere('id', $value)['name'] ?? null,
            CustomFieldType::Checkbox => 'Yes',
            default => (string) $value,
        };
    }

    /**
     * Get the field as an array for the frontend.
     *
     * @return array{id: int, name: string, type: string, options: array<int, array{id: string, name: string, color: string}>, hidden_from_clients: bool}
     */
    public function toFieldData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'options' => $this->options ?? [],
            'hidden_from_clients' => $this->hidden_from_clients,
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
            'type' => CustomFieldType::class,
            'options' => 'array',
            'hidden_from_clients' => 'boolean',
        ];
    }
}
