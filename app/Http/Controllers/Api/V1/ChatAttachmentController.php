<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ChatAttachmentResource;
use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Services\Documents\DocumentTextExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatAttachmentController extends BaseApiController
{
    private const MAX_ATTACHMENTS_PER_CONVERSATION = 5;

    public function index(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $attachments = $conversation->attachments()
            ->where('user_id', auth('api')->id())
            ->latest()
            ->get();

        return $this->success(ChatAttachmentResource::collection($attachments));
    }

    public function store(Request $request, DocumentTextExtractor $extractor): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,txt,jpg,jpeg,png,webp',
            ],
        ]);

        $conversation = Conversation::where('id', $validated['conversation_id'])
            ->where('user_id', auth('api')->id())
            ->first();

        if (! $conversation) {
            return $this->forbidden('Conversation not found or access denied.');
        }

        if ($conversation->attachments()->count() >= self::MAX_ATTACHMENTS_PER_CONVERSATION) {
            return $this->error('Maximum 5 attachments are allowed per conversation.', 422);
        }

        $file = $request->file('file');
        $path = $file->store("chat-attachments/{$conversation->id}", 'public');

        $attachment = ChatAttachment::create([
            'user_id' => auth('api')->id(),
            'conversation_id' => $conversation->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
        ]);

        $extractedText = $this->extractPreviewText($extractor, $path, $attachment->mime_type);
        if ($extractedText !== '') {
            $attachment->forceFill([
                'status' => 'processed',
                'extracted_text' => $extractedText,
            ])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded.',
            'status' => 'uploaded',
            'data' => new ChatAttachmentResource($attachment),
        ], 201);
    }

    public function destroy(ChatAttachment $attachment): JsonResponse
    {
        if ($attachment->user_id !== auth('api')->id()) {
            return $this->forbidden('Attachment not found or access denied.');
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return $this->success(null, 'Attachment deleted.');
    }

    private function extractPreviewText(
        DocumentTextExtractor $extractor,
        string $path,
        ?string $mimeType
    ): string {
        if (str_starts_with((string) $mimeType, 'image/')) {
            return '';
        }

        try {
            $pages = $extractor->extractFromStoragePath($path, $mimeType, 'public');
        } catch (\Throwable) {
            return '';
        }

        $text = collect($pages)
            ->map(fn (array $page): string => trim((string) ($page['text'] ?? '')))
            ->filter()
            ->join("\n\n");

        return mb_substr($text, 0, 5000);
    }
}
