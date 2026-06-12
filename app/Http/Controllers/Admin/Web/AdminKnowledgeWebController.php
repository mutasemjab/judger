<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KnowledgeDocumentResource;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminKnowledgeWebController extends Controller
{
    public function index(Request $request)
    {
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
        ];

        return view('admin.knowledge.index', compact('documents', 'stats'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'     => KnowledgeDocument::uploadRules(),
            'title'    => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
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

        ProcessKnowledgeDocumentJob::dispatch($document->id);

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json((new KnowledgeDocumentResource($document))->resolve(), 201);
        }

        return back()->with('success', __('messages.saved_success'));
    }

    public function reprocess(KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $knowledgeDocument->update([
            'status' => 'uploaded',
            'processing_error' => null,
            'processed_at' => null,
            'qdrant_points_count' => 0,
        ]);

        ProcessKnowledgeDocumentJob::dispatch($knowledgeDocument->id);

        return back()->with('success', __('messages.updated_success'));
    }

    public function destroy(KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $knowledgeDocument->delete();

        return back()->with('success', __('messages.deleted_success'));
    }

    public function statuses(Request $request): JsonResponse
    {
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
}
