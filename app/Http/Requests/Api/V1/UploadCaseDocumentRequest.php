<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadCaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,docx,txt,doc,png,jpg,jpeg|max:20480',
            'document_type' => 'nullable|string|max:100',
        ];
    }
}
