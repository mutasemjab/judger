<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\AiToolOutput;
use App\Models\CaseDocument;
use App\Models\KnowledgeDocument;
use App\Models\LegalCase;
use App\Models\Message;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success([
            'total_users' => User::count(),
            'active_users' => User::where('account_status', 'active')->count(),
            'total_cases' => LegalCase::count(),
            'total_documents' => CaseDocument::count(),
            'total_ai_messages' => Message::where('role', 'assistant')->count(),
            'total_knowledge_documents' => KnowledgeDocument::count(),
            'failed_documents_count' => CaseDocument::where('status', 'failed')->count(),
            'active_subscriptions' => UserSubscription::where('status', 'active')->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)->count(),
            'new_cases_this_month' => LegalCase::whereMonth('created_at', now()->month)->count(),
        ]);
    }
}
