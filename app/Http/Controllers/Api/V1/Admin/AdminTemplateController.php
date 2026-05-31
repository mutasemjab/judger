<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\TemplateResource;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminTemplateController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $templates = Template::with('category')
            ->when($request->search, fn($q, $v) => $q->where('title', 'LIKE', "%{$v}%"))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($templates, TemplateResource::class)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_category_id' => 'required|integer|exists:template_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        $validated['created_by'] = auth('api')->id();

        $template = Template::create($validated);
        return $this->created(new TemplateResource($template), 'Template created.');
    }

    public function update(Request $request, Template $template): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($validated);
        return $this->success(new TemplateResource($template->fresh()), 'Template updated.');
    }

    public function destroy(Template $template): JsonResponse
    {
        $template->delete();
        return $this->success(null, 'Template deleted.');
    }
}
