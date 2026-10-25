<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ProfileAvatarRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The type check runs against the detected MIME type, not the name the
     * browser sent, so renaming a script to .png doesn't get it through.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', File::image()->types(User::AVATAR_TYPES)->max(User::AVATAR_MAX_KILOBYTES)],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.image' => __('The photo must be a PNG, JPEG, GIF or WebP image.'),
            'avatar.mimes' => __('The photo must be a PNG, JPEG, GIF or WebP image.'),
            'avatar.max' => __('The photo must be 2 MB or smaller.'),
        ];
    }
}
