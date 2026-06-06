<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminActivityLogWebController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user')
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->action, fn($q, $v) => $q->where('action', $v))
            ->latest()
            ->paginate(50);

        $users = User::select('id', 'name', 'email')->get();

        return view('admin.activity-logs.index', compact('logs', 'users'));
    }
}
