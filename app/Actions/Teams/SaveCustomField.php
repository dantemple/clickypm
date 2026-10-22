<?php

namespace App\Actions\Teams;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Team;
use App\Support\LiveUpdates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveCustomField
{
    public function __construct(private LiveUpdates $liveUpdates)
    {
        //
    }

    /**
     * Add a field to the workspace.
     *
     * @param  array<int, array{id?: string|null, name: string, color: string}>|null  $options
     */
    public function create(Team $team, string $name, CustomFieldType $type, bool $hiddenFromClients = false, ?array $options = null): CustomField
    {
        $field = $team->customFields()->make(['type' => $type]);

        $this->fill($field, array_filter([
            'name' => $name,
            'hidden_from_clients' => $hiddenFromClients,
            'options' => $options,
        ], fn ($value) => $value !== null));
        $field->save();

        return $field;
    }

    /**
     * Update a field. Its type is fixed once created.
     *
     * Dropdown options keep their ids, so tasks keep their choice when an
     * option is renamed; tasks set to a removed option are cleared.
     *
     * @param  array{name?: string, hidden_from_clients?: bool, options?: array<int, array{id?: string|null, name: string, color: string}>}  $attributes
     */
    public function update(CustomField $field, array $attributes): CustomField
    {
        return DB::transaction(function () use ($field, $attributes) {
            $this->fill($field, $attributes);
            $field->save();

            if ($field->type === CustomFieldType::Dropdown) {
                $optionIds = array_column($field->options ?? [], 'id');

                $field->values()->get()
                    ->reject(fn (CustomFieldValue $value) => in_array($value->value, $optionIds, true))
                    ->each->delete();
            }

            $this->notifyProjects($field);

            return $field;
        });
    }

    /**
     * Delete a field and every task's value for it.
     */
    public function delete(CustomField $field): void
    {
        $this->notifyProjects($field);

        $field->delete();
    }

    /**
     * Tell everyone viewing a project that uses the field to refresh.
     */
    public function notifyProjects(CustomField $field): void
    {
        $field->projects()->pluck('projects.id')->each(fn (int $id) => $this->liveUpdates->projectChanged($id));
    }

    /**
     * Set the field's name, visibility and (for dropdowns) options.
     *
     * @param  array{name?: string, hidden_from_clients?: bool, options?: array<int, array{id?: string|null, name: string, color: string}>}  $attributes
     */
    private function fill(CustomField $field, array $attributes): void
    {
        $field->fill(collect($attributes)->only(['name', 'hidden_from_clients'])->all());

        if ($field->type === CustomFieldType::Dropdown && isset($attributes['options'])) {
            $field->options = $this->withIds($attributes['options'], $field->options ?? []);
        }
    }

    /**
     * Keep the ids of options that already exist; give new ones an id.
     *
     * @param  array<int, array{id?: string|null, name: string, color: string}>  $options
     * @param  array<int, array{id: string, name: string, color: string}>  $current
     * @return array<int, array{id: string, name: string, color: string}>
     */
    private function withIds(array $options, array $current): array
    {
        $known = array_column($current, 'id');

        return array_map(fn (array $option) => [
            'id' => in_array($option['id'] ?? null, $known, true) ? $option['id'] : Str::lower(Str::random(8)),
            'name' => $option['name'],
            'color' => $option['color'],
        ], array_values($options));
    }
}
