<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\UploadKnowledgeDocumentRequest;
use App\Http\Resources\Api\V1\KnowledgeDocumentResource;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminKnowledgeDocumentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $documents = KnowledgeDocument::with('uploadedBy')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($documents, KnowledgeDocumentResource::class)
        );
    }

    public function store(UploadKnowledgeDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('knowledge_documents', $fileName);

        $document = KnowledgeDocument::create([
            'title' => $request->title,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'file_path' => $path,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $request->category,
            'uploaded_by' => auth('api')->id(),
        ]);

        ProcessKnowledgeDocumentJob::dispatch($document->id);

        return $this->created(new KnowledgeDocumentResource($document), 'Document uploaded and queued for indexing.');
    }

    public function show(KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        return $this->success(new KnowledgeDocumentResource($knowledgeDocument->load('uploadedBy')));
    }

    public function destroy(KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        $knowledgeDocument->delete();
        return $this->success(null, 'Knowledge document deleted.');
    }

    public function reprocess(KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        ProcessKnowledgeDocumentJob::dispatch($knowledgeDocument->id);
        return $this->success(null, 'Reprocessing queued.');
    }
}
