<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TemplateResource;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\TemplateFavorite;
use App\Services\Documents\GeneratedFileExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends BaseApiController
{
    public function __construct(private GeneratedFileExportService $exportService) {}

    public function categories(): JsonResponse
    {
        $categories = TemplateCategory::withCount('templates')->get();
        return $this->success($categories);
    }

    public function index(Request $request): JsonResponse
    {
        $templates = Template::where('is_active', true)
            ->with('category')
            ->when($request->category, fn($q, $v) => $q->whereHas('category', fn($q2) => $q2->where('slug', $v)))
            ->when($request->search, fn($q, $v) => $q->where('title', 'LIKE', "%{$v}%"))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($templates, TemplateResource::class)
        );
    }

    public function show(Template $template): JsonResponse
    {
        return $this->success(new TemplateResource($template->load('category')));
    }

    public function favorite(Template $template): JsonResponse
    {
        $userId = auth('api')->id();
        $favorite = TemplateFavorite::where('user_id', $userId)->where('template_id', $template->id)->first();

        if ($favorite) {
            $favorite->delete();
            return $this->success(null, 'Template removed from favorites.');
        }

        TemplateFavorite::create(['user_id' => $userId, 'template_id' => $template->id]);
        return $this->success(null, 'Template added to favorites.');
    }

    public function generate(Request $request, Template $template): JsonResponse
    {
        $request->validate([
            'variables' => 'nullable|array',
            'legal_case_id' => 'nullable|integer|exists:legal_cases,id',
        ]);

        $content = $template->content;
        $variables = $request->variables ?? [];

        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        $disclaimer = config('ai.legal_disclaimer');

        $document = GeneratedDocument::create([
            'user_id' => auth('api')->id(),
            'legal_case_id' => $request->legal_case_id,
            'template_id' => $template->id,
            'title' => $template->title,
            'content' => $content,
            'variables' => $variables,
            'disclaimer' => $disclaimer,
        ]);

        $download = $this->exportService->exportGeneratedDocument($document);

        return $this->created([
            'id' => $document->id,
            'title' => $document->title,
            'content' => $document->content,
            'disclaimer' => $disclaimer,
            'download' => $this->exportService->publicDownloadData($download),
            'download_url' => $download['url'] ?? null,
            'actions' => [[
                'id' => 'download_docx',
                'type' => 'download',
                'label' => 'Download Word file',
                'style' => 'primary',
                'url' => $download['url'] ?? null,
                'format' => $download['format'] ?? 'docx',
                'file_name' => $download['file_name'] ?? null,
            ]],
        ], 'Document generated.');
    }

    public function download(GeneratedDocument $document): mixed
    {
        if ((int) $document->user_id !== (int) auth('api')->id()) {
            return $this->forbidden('You do not have access to this generated document.');
        }

        $download = $this->exportService->exportGeneratedDocument($document);

        return $this->exportService->downloadResponse($download);
    }
}
