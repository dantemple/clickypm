<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateApiTokenRequest extends FormRequest
{
    /**
     * How long a token can be set to last, in days.
     *
     * @var array<int, int>
     */
    public const LIFETIMES = [30, 90, 365];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Blank means the token never expires.
            'expires_in_days' => ['nullable', 'integer', 'in:'.implode(',', self::LIFETIMES)],
            'read_only' => ['sometimes', 'boolean'],
        ];
    }
}
