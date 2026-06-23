<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:4000',
            'attachment_ids' => 'sometimes|array|max:5',
            'attachment_ids.*' => 'integer|exists:chat_attachments,id',
        ];
    }
}
