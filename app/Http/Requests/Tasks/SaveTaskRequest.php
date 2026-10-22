<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Models\Attachment;
use App\Models\CustomField;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Every field is optional on update so inline edits can send just one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        $fields = $project->customFields;

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'status_id' => ['sometimes', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)],
            'priority' => ['sometimes', 'nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('team_members', 'user_id')->where('team_id', $project->team_id)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES],
            // Values keyed by field id, for fields the project uses.
            'custom_fields' => $fields->isEmpty()
                ? ['prohibited']
                : ['sometimes', 'array:'.$fields->implode('id', ',')],
            ...$fields->mapWithKeys(fn (CustomField $field) => ["custom_fields.{$field->id}" => $field->valueRules()]),
        ];
    }
}
