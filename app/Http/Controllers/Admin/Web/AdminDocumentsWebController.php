<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCaseDocumentJob;
use App\Models\CaseDocument;
use Illuminate\Http\Request;

class AdminDocumentsWebController extends Controller
{
    public function index(Request $request)
    {
        $documents = CaseDocument::with('user', 'legalCase')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);

        return view('admin.documents.index', compact('documents'));
    }

    public function reprocess(CaseDocument $document)
    {
        ProcessCaseDocumentJob::dispatch($document->id);
        return back()->with('success', __('messages.updated_success'));
    }

    public function destroy(CaseDocument $document)
    {
        $document->delete();
        return back()->with('success', __('messages.deleted_success'));
    }
}
