<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:google,apple',
            'id_token' => 'required|string',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'user_type' => 'nullable|in:' . implode(',', UserType::values()),
        ];
    }
}
