<?php

namespace App\Http\Requests\Projects;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveProjectRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'hex_color'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'hidden_from_clients' => ['sometimes', 'boolean'],
        ];
    }
}
