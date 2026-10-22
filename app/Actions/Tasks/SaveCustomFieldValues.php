<?php

namespace App\Actions\Tasks;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class SaveCustomFieldValues
{
    /**
     * Set or clear the task's values for the given fields, keyed by field id.
     *
     * Only fields the task's project uses are touched. Each change goes in the
     * task's history, and the task is touched so viewers see it live.
     *
     * @param  array<int|string, mixed>  $values
     */
    public function handle(Task $task, array $values): void
    {
        if ($values === []) {
            return;
        }

        DB::transaction(function () use ($task, $values) {
            $fields = $task->project->customFields()->whereKey(array_keys($values))->get();
            $existing = $task->customFieldValues()->whereIn('custom_field_id', $fields->modelKeys())->get()->keyBy('custom_field_id');
            $changed = false;

            foreach ($fields as $field) {
                $old = $existing->get($field->id);
                $new = $field->normalize($values[$field->id]);

                if ($old?->value === $new) {
                    continue;
                }

                if ($new === null) {
                    $old?->delete();
                } elseif ($old) {
                    $old->update(['value' => $new]);
                } else {
                    $task->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => $new]);
                }

                $this->recordChange($task, $field, $old?->value, $new);
                $changed = true;
            }

            if ($changed) {
                $task->touch();
            }
        });
    }

    /**
     * Add the change to the task's history.
     */
    private function recordChange(Task $task, CustomField $field, mixed $from, mixed $to): void
    {
        $task->recordActivity('custom_field', [
            // Kept so the entry can be hidden from clients with the field.
            'field_id' => $field->id,
            'name' => $field->name,
            'type' => $field->type->value,
            'from' => $field->type === CustomFieldType::Checkbox ? null : $field->describe($from),
            'to' => $field->describe($to),
        ]);
    }
}
