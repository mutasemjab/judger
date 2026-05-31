<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AiToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => 'nullable|string|max:10000',
            'query' => 'nullable|string|max:2000',
            'legal_case_id' => 'nullable|integer|exists:legal_cases,id',
            'case_document_id' => 'nullable|integer|exists:case_documents,id',
            'additional_info' => 'nullable|string|max:5000',
        ];
    }
}
