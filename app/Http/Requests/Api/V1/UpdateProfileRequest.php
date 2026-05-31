<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:2048',
            'language' => 'nullable|string|max:10',
            'theme' => 'nullable|in:light,dark,system',
            'notification_preferences' => 'nullable|array',
            'biometric_enabled' => 'nullable|boolean',
        ];
    }
}
