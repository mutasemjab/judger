<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\TemplateCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminTemplateWebController extends Controller
{
    public function index(Request $request)
    {
        $templates = Template::with('category')
            ->when($request->search, fn($q, $v) => $q->where('title', 'LIKE', "%{$v}%"))
            ->when($request->category_id, fn($q, $v) => $q->where('template_category_id', $v))
            ->latest()
            ->paginate(20);

        $categories = TemplateCategory::all();

        return view('admin.templates.index', compact('templates', 'categories'));
    }

    public function create()
    {
        $categories = TemplateCategory::all();
        return view('admin.templates.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_category_id' => 'required|integer|exists:template_categories,id',
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'content'              => 'required|string',
            'variables'            => 'nullable|array',
            'is_active'            => 'nullable|boolean',
        ]);

        $validated['slug']       = Str::slug($validated['title']) . '-' . Str::random(6);
        $validated['created_by'] = Auth::guard('admin_web')->id();
        $validated['is_active']  = $request->boolean('is_active', true);

        Template::create($validated);

        return redirect()->route('admin.templates.index')->with('success', __('messages.saved_success'));
    }

    public function edit(Template $template)
    {
        $categories = TemplateCategory::all();
        return view('admin.templates.edit', compact('template', 'categories'));
    }

    public function update(Request $request, Template $template)
    {
        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'content'     => 'sometimes|string',
            'variables'   => 'nullable|array',
            'is_active'   => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $template->update($validated);

        return redirect()->route('admin.templates.index')->with('success', __('messages.updated_success'));
    }

    public function destroy(Template $template)
    {
        $template->delete();
        return back()->with('success', __('messages.deleted_success'));
    }
}
