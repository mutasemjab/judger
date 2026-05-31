<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreLegalCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:500',
            'category' => 'nullable|string|max:100',
            'case_number' => 'nullable|string|max:100',
            'court' => 'nullable|string|max:255',
            'court_name' => 'nullable|string|max:255',
            'jurisdiction' => 'nullable|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'opposing_party' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', CaseStatus::values()),
            'priority' => 'nullable|in:' . implode(',', CasePriority::values()),
            'next_hearing_at' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'tags' => 'nullable|array',
        ];
    }
}
