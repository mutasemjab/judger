<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UploadCaseDocumentRequest;
use App\Http\Resources\Api\V1\CaseDocumentResource;
use App\Jobs\ProcessCaseDocumentJob;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaseDocumentController extends BaseApiController
{
    public function index(LegalCase $case): JsonResponse
    {
        $this->authorize('view', $case);
        $documents = $case->documents()->orderByDesc('created_at')->paginate(15);
        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($documents, CaseDocumentResource::class)
        );
    }

    public function store(UploadCaseDocumentRequest $request, LegalCase $case): JsonResponse
    {
        $this->authorize('view', $case);

        $file = $request->file('file');
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('case_documents/' . $case->id, $fileName);

        $document = CaseDocument::create([
            'legal_case_id' => $case->id,
            'user_id' => auth('api')->id(),
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'file_path' => $path,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => $request->document_type,
        ]);

        ProcessCaseDocumentJob::dispatch($document->id);

        return $this->created(new CaseDocumentResource($document), 'Document uploaded and queued for processing.');
    }

    public function show(LegalCase $case, CaseDocument $document): JsonResponse
    {
        $this->authorize('view', $document);
        return $this->success(new CaseDocumentResource($document->load('insight')));
    }

    public function destroy(LegalCase $case, CaseDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);
        Storage::delete($document->file_path);
        $document->delete();
        return $this->success(null, 'Document deleted.');
    }

    public function download(LegalCase $case, CaseDocument $document): mixed
    {
        $this->authorize('download', $document);

        if (!Storage::exists($document->file_path)) {
            return $this->notFound('File not found.');
        }

        return Storage::download($document->file_path, $document->original_name);
    }

    public function reprocess(LegalCase $case, CaseDocument $document): JsonResponse
    {
        $this->authorize('view', $document);
        ProcessCaseDocumentJob::dispatch($document->id);
        return $this->success(null, 'Document reprocessing queued.');
    }

    public function analyze(LegalCase $case, CaseDocument $document): JsonResponse
    {
        $this->authorize('view', $document);
        ProcessCaseDocumentJob::dispatch($document->id);
        return $this->success(null, 'Document analysis queued.');
    }
}
