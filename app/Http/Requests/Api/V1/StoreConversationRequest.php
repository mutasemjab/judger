<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ConversationType;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:' . implode(',', ConversationType::values()),
            'legal_case_id' => 'required_if:type,case|nullable|integer|exists:legal_cases,id',
            'title' => 'nullable|string|max:255',
        ];
    }
}
