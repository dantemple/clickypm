<?php

namespace App\Http\Requests\Teams;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomFieldRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A field's type is chosen when it's created and can't change after.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('team');
        /** @var CustomField|null $field */
        $field = $this->route('customField');

        $type = $field->type ?? CustomFieldType::tryFrom((string) $this->input('type'));
        $options = match (true) {
            $type !== CustomFieldType::Dropdown => 'prohibited',
            $field === null => 'required',
            default => 'sometimes',
        };

        return [
            'name' => [
                $field ? 'sometimes' : 'required', 'string', 'max:100',
                Rule::unique('custom_fields')->where('team_id', $team->id)->ignore($field),
            ],
            'type' => $field ? ['prohibited'] : ['required', Rule::enum(CustomFieldType::class)],
            'hidden_from_clients' => ['sometimes', 'boolean'],
            'options' => [$options, 'array', 'min:1', 'max:50'],
            'options.*.id' => ['nullable', 'string', 'max:20'],
            'options.*.name' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'options.*.color' => ['required', 'hex_color'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This workspace already has a field with that name.'),
            'options.required' => __('Add at least one option.'),
            'options.*.name.required' => __('Every option needs a name.'),
            'options.*.name.distinct' => __('Option names must be different.'),
        ];
    }
}
