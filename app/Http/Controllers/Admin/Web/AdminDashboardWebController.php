<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\CaseDocument;
use App\Models\KnowledgeDocument;
use App\Models\LegalCase;
use App\Models\Message;
use App\Models\User;
use App\Models\UserSubscription;

class AdminDashboardWebController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users'          => User::count(),
            'active_users'         => User::where('account_status', 'active')->count(),
            'total_cases'          => LegalCase::count(),
            'total_documents'      => CaseDocument::count(),
            'total_ai_messages'    => Message::where('role', 'assistant')->count(),
            'knowledge_documents'  => KnowledgeDocument::count(),
            'active_subscriptions' => UserSubscription::where('status', 'active')->count(),
            'failed_documents'     => CaseDocument::where('status', 'failed')->count(),
            'new_users_month'      => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'new_cases_month'      => LegalCase::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        $recentUsers = User::with('subscription.plan')->latest()->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recentUsers'));
    }
}
