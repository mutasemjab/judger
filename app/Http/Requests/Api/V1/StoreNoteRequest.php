<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\NoteType;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:500',
            'content' => 'required|string',
            'type' => 'nullable|in:' . implode(',', NoteType::values()),
            'legal_case_id' => 'nullable|integer|exists:legal_cases,id',
            'is_pinned' => 'nullable|boolean',
        ];
    }
}
