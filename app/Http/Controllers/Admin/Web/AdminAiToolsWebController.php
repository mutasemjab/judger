<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\AiToolOutput;
use Illuminate\Http\Request;

class AdminAiToolsWebController extends Controller
{
    public function index(Request $request)
    {
        $outputs = AiToolOutput::with('user', 'legalCase')
            ->when($request->tool_type, fn($q, $v) => $q->where('tool_type', $v))
            ->latest()
            ->paginate(20);

        return view('admin.ai-tools.index', compact('outputs'));
    }
}
