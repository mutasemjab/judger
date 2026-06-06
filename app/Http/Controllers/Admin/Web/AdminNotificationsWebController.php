<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class AdminNotificationsWebController extends Controller
{
    public function index(Request $request)
    {
        $notifications = UserNotification::with('user')
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->read !== null && $request->read !== '', function ($q) use ($request) {
                $request->read === '1'
                    ? $q->whereNotNull('read_at')
                    : $q->whereNull('read_at');
            })
            ->latest()
            ->paginate(30);

        return view('admin.notifications.index', compact('notifications'));
    }
}
