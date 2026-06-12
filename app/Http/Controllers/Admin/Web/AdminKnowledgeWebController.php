<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\KnowledgeDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KnowledgeDocumentResource;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use App\Services\Vector\Contracts\VectorStoreInterface;
use App\Services\Vector\VectorStoreManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AdminKnowledgeWebController extends Controller
{
    public function index(Request $request)
    {
        $this->recoverStaleProcessingDocuments();

        $documents = KnowledgeDocument::with('uploadedBy')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => KnowledgeDocument::query()->count(),
            'uploaded' => KnowledgeDocument::query()->where('status', 'uploaded')->count(),
            'processing' => KnowledgeDocument::query()->where('status', 'processing')->count(),
            'processed' => KnowledgeDocument::query()->where('status', 'processed')->count(),
            'failed' => KnowledgeDocument::query()->where('status', 'failed')->count(),
            'cancelled' => KnowledgeDocument::query()->where('status', 'cancelled')->count(),
        ];

        $vectorStore = app(VectorStoreManager::class);
        $vectorIndex = [
            'driver' => $vectorStore->driver(),
            'label' => $vectorStore->label(),
        ];

        return view('admin.knowledge.index', compact('documents', 'stats', 'vectorIndex'));
    }

    public function store(Request $request)
    {
        $isAsyncBatchRequest = $request->expectsJson() || $request->wantsJson() || $request->ajax();

        $request->validate([
            'file'     => KnowledgeDocument::uploadRules(),
            'title'    => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'process_now' => 'nullable|boolean',
        ]);

        $file     = $request->file('file');
        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path     = $file->storeAs('knowledge_documents', $fileName);

        $document = KnowledgeDocument::create([
            'title'         => KnowledgeDocument::normalizeTitle($request->title, $file->getClientOriginalName()),
            'original_name' => $file->getClientOriginalName(),
            'file_name'     => $fileName,
            'file_path'     => $path,
            'disk'          => 'local',
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'category'      => $request->category,
            'uploaded_by'   => Auth::guard('admin_web')->id(),
        ]);

        if ($request->boolean('process_now')) {
            try {
                $document = $this->runDocumentProcessing($request, $document);
            } catch (RuntimeException $exception) {
                if ($isAsyncBatchRequest) {
                    return response()->json([
                        'message' => $exception->getMessage(),
                        'data' => (new KnowledgeDocumentResource($document->fresh('uploadedBy')))->resolve(),
                    ], 409);
                }

                return back()->with('error', $exception->getMessage());
            }
        }

        if ($isAsyncBatchRequest) {
            return response()->json((new KnowledgeDocumentResource($document->fresh('uploadedBy')))->resolve(), 201);
        }

        return back()->with('success', __('messages.saved_success'));
    }

    public function reprocess(Request $request, KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        try {
            $knowledgeDocument = $this->runDocumentProcessing($request, $knowledgeDocument, true);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            $knowledgeDocument->status?->value === 'processed' ? 'success' : 'error',
            $knowledgeDocument->status?->value === 'processed'
                ? __('messages.updated_success')
                : ($knowledgeDocument->processing_error ?: __('messages.failed'))
        );
    }

    public function processNow(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        try {
            $document = $this->runDocumentProcessing($request, $knowledgeDocument);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => (new KnowledgeDocumentResource($knowledgeDocument->fresh('uploadedBy')))->resolve(),
            ], 409);
        }

        return response()->json((new KnowledgeDocumentResource($document))->resolve());
    }

    public function stop(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse|RedirectResponse
    {
        $this->recoverStaleProcessingDocuments();

        if ($knowledgeDocument->canStopProcessing()) {
            $knowledgeDocument->update([
                'stop_requested_at' => now(),
                'processing_error' => __('messages.stop_requested_message'),
            ]);
        }

        $knowledgeDocument->refresh()->load('uploadedBy');

        if ($this->shouldUseJson($request)) {
            return response()->json([
                'message' => __('messages.stop_requested'),
                'data' => (new KnowledgeDocumentResource($knowledgeDocument))->resolve(),
            ]);
        }

        return back()->with('success', __('messages.stop_requested'));
    }

    public function destroy(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse|RedirectResponse
    {
        $this->recoverStaleProcessingDocuments();

        $wasProcessing = $knowledgeDocument->canStopProcessing();

        if ($wasProcessing) {
            $knowledgeDocument->update([
                'stop_requested_at' => now(),
                'processing_error' => __('messages.processing_deleted_message'),
            ]);
        }

        $knowledgeDocument->delete();

        if ($this->shouldUseJson($request)) {
            return response()->json([
                'message' => $wasProcessing
                    ? __('messages.delete_requested')
                    : __('messages.deleted_success'),
            ]);
        }

        return back()->with('success', __('messages.deleted_success'));
    }

    public function statuses(Request $request): JsonResponse
    {
        $this->recoverStaleProcessingDocuments();

        $ids = collect(array_merge(
            explode(',', (string) $request->query('ids', '')),
            (array) $request->input('ids', [])
        ))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(500)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $documents = KnowledgeDocument::with('uploadedBy')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $data = $ids->map(function (int $id) use ($documents): ?array {
            $document = $documents->get($id);

            return $document ? (new KnowledgeDocumentResource($document))->resolve() : null;
        })->filter()->values();

        return response()->json(['data' => $data]);
    }

    private function runDocumentProcessing(
        Request $request,
        KnowledgeDocument $knowledgeDocument,
        bool $forceRestart = false
    ): KnowledgeDocument {
        $this->recoverStaleProcessingDocuments();
        $knowledgeDocument->refresh();

        if ($knowledgeDocument->status === KnowledgeDocumentStatus::Processing) {
            throw new RuntimeException(__('messages.document_already_processing'));
        }

        $activeDocument = KnowledgeDocument::query()
            ->where('status', KnowledgeDocumentStatus::Processing->value)
            ->whereKeyNot($knowledgeDocument->id)
            ->latest('processing_started_at')
            ->first();

        if ($activeDocument) {
            throw new RuntimeException(__('messages.another_document_processing', [
                'title' => $activeDocument->title,
            ]));
        }

        if ($forceRestart || $knowledgeDocument->status !== KnowledgeDocumentStatus::Uploaded) {
            $this->resetDocumentForProcessing($knowledgeDocument);
        }

        $this->releaseSessionLock($request);

        return ProcessKnowledgeDocumentJob::processNow($knowledgeDocument->id);
    }

    private function resetDocumentForProcessing(KnowledgeDocument $knowledgeDocument): void
    {
        $knowledgeDocument->update([
            'status' => KnowledgeDocumentStatus::Uploaded->value,
            'processing_error' => null,
            'processing_started_at' => null,
            'stop_requested_at' => null,
            'processed_at' => null,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'total_chunks_count' => 0,
        ]);
    }

    private function releaseSessionLock(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->save();
        }
    }

    private function shouldUseJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->ajax();
    }

    private function recoverStaleProcessingDocuments(): void
    {
        $staleBefore = now()->subMinutes(15);

        $documents = KnowledgeDocument::query()
            ->where('status', KnowledgeDocumentStatus::Processing->value)
            ->where(function ($query) use ($staleBefore) {
                $query->where('updated_at', '<=', $staleBefore)
                    ->orWhere(function ($inner) use ($staleBefore) {
                        $inner->whereNull('updated_at')
                            ->where('processing_started_at', '<=', $staleBefore);
                    });
            })
            ->get();

        if ($documents->isEmpty()) {
            return;
        }

        foreach ($documents as $document) {
            try {
                app(VectorStoreInterface::class)->deleteByFilter(
                    $document->qdrant_collection ?: config('ai.qdrant_knowledge_collection'),
                    ['knowledge_document_id' => $document->id]
                );
            } catch (Throwable) {
                // Keep the stale-status recovery resilient even if cleanup fails.
            }

            $document->update([
                'status' => $document->stop_requested_at
                    ? KnowledgeDocumentStatus::Cancelled->value
                    : KnowledgeDocumentStatus::Failed->value,
                'qdrant_points_count' => 0,
                'processed_chunks_count' => 0,
                'total_chunks_count' => 0,
                'processed_at' => null,
                'processing_error' => $document->stop_requested_at
                    ? __('messages.stop_requested_message')
                    : __('messages.processing_timed_out'),
            ]);
        }
    }
}
