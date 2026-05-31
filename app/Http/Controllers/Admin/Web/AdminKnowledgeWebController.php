<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
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

        return view('admin.knowledge.index', compact('documents'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:pdf,docx,txt,doc|max:51200',
            'title'    => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);

        $file     = $request->file('file');
        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path     = $file->storeAs('knowledge_documents', $fileName);

        $document = KnowledgeDocument::create([
            'title'         => $request->title,
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

        return back()->with('success', __('messages.saved_success'));
    }

    public function reprocess(KnowledgeDocument $knowledgeDocument)
    {
        ProcessKnowledgeDocumentJob::dispatch($knowledgeDocument->id);
        return back()->with('success', __('messages.updated_success'));
    }

    public function destroy(KnowledgeDocument $knowledgeDocument)
    {
        $knowledgeDocument->delete();
        return back()->with('success', __('messages.deleted_success'));
    }
}
