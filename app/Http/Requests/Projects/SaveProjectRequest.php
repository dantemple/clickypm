<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    /**
     * Keys are typed in any case; they're stored in capitals.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('key'))) {
            $this->merge(['key' => strtoupper(trim($this->input('key')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $team = $this->route('current_team');

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'sometimes',
                'string',
                'min:2',
                'max:10',
                'regex:/^[A-Z][A-Z0-9]*$/',
                Rule::unique('projects', 'key')
                    ->where('team_id', $team instanceof Team ? $team->id : null)
                    ->ignore($project instanceof Project ? $project->id : null),
            ],
            'color' => ['sometimes', 'nullable', 'hex_color'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'hidden_from_clients' => ['sometimes', 'boolean'],
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
            'key.regex' => __('The key must be letters and numbers, starting with a letter.'),
            'key.unique' => __('Another project in this workspace already uses that key.'),
        ];
    }
}
