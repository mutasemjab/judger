<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\HearingStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreHearingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'reminder_at' => 'nullable|date',
            'type' => 'nullable|string|max:100',
            'status' => 'nullable|in:' . implode(',', HearingStatus::values()),
            'legal_case_id' => 'nullable|integer|exists:legal_cases,id',
        ];
    }
}
