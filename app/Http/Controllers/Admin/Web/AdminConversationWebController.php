<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class AdminConversationWebController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::with('user', 'legalCase')
            ->withCount('messages')
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->latest()
            ->paginate(20);

        return view('admin.conversations.index', compact('conversations'));
    }
}
