<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', TaskStatus::values()),
            'priority' => 'nullable|in:' . implode(',', TaskPriority::values()),
            'due_date' => 'nullable|date',
            'legal_case_id' => 'nullable|integer|exists:legal_cases,id',
        ];
    }
}
