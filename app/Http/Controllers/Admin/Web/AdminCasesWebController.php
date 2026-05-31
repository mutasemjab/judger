<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use Illuminate\Http\Request;

class AdminCasesWebController extends Controller
{
    public function index(Request $request)
    {
        $cases = LegalCase::with('user')
            ->withCount('documents')
            ->when($request->search, fn($q) => $q->where(fn($q2) =>
                $q2->where('title', 'LIKE', '%'.$request->search.'%')
                   ->orWhere('case_number', 'LIKE', '%'.$request->search.'%')
            ))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->priority, fn($q, $v) => $q->where('priority', $v))
            ->latest()
            ->paginate(20);

        return view('admin.cases.index', compact('cases'));
    }
}
