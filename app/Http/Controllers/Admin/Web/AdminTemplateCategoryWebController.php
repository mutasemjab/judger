<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\TemplateCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminTemplateCategoryWebController extends Controller
{
    public function index()
    {
        $categories = TemplateCategory::withCount('templates')->get();

        return view('admin.template-categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:template_categories,slug',
            'description' => 'nullable|string|max:500',
        ]);

        TemplateCategory::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->slug),
            'description' => $request->description,
        ]);

        return back()->with('success', __('messages.saved_success'));
    }

    public function update(Request $request, TemplateCategory $templateCategory)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $templateCategory->update($request->only('name', 'description'));

        return back()->with('success', __('messages.updated_success'));
    }
}
