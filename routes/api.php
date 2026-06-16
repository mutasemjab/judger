<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AiToolController;
use App\Http\Controllers\Api\V1\CaseDocumentController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\HearingController;
use App\Http\Controllers\Api\V1\LegalCaseController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Public Auth ──────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

    // ── Authenticated Routes ──────────────────────────────────────────────
    Route::middleware(['auth:api', 'account.active'])->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);

        // Profile
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateMe']);
        Route::put('/me/password', [AuthController::class, 'changePassword']);

        // Cases
        Route::apiResource('cases', LegalCaseController::class);
        Route::get('/cases/{case}/overview', [LegalCaseController::class, 'overview']);

        // Case Documents
        Route::prefix('cases/{case}/documents')->group(function () {
            Route::get('/', [CaseDocumentController::class, 'index']);
            Route::post('/', [CaseDocumentController::class, 'store'])->middleware('throttle:20,1');
            Route::get('/{document}', [CaseDocumentController::class, 'show']);
            Route::delete('/{document}', [CaseDocumentController::class, 'destroy']);
            Route::get('/{document}/download', [CaseDocumentController::class, 'download']);
            Route::post('/{document}/reprocess', [CaseDocumentController::class, 'reprocess']);
            Route::post('/{document}/analyze', [CaseDocumentController::class, 'analyze']);
        });

        // Conversations
        Route::apiResource('conversations', ConversationController::class)->except(['update']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/chat', [ConversationController::class, 'chat'])->middleware('throttle:60,1');

        // Messages
        Route::post('/messages/{message}/pin', [MessageController::class, 'pin']);
        Route::post('/messages/{message}/save-as-note', [MessageController::class, 'saveAsNote']);
        Route::get('/messages/{message}/download', [MessageController::class, 'download']);

        // AI Tools
        Route::prefix('ai-tools')->middleware('throttle:30,1')->group(function () {
            Route::post('/case-summarizer', [AiToolController::class, 'caseSummarizer']);
            Route::post('/document-summarizer', [AiToolController::class, 'documentSummarizer']);
            Route::post('/contract-analyzer', [AiToolController::class, 'contractAnalyzer']);
            Route::post('/risk-estimator', [AiToolController::class, 'riskEstimator']);
            Route::post('/memo-generator', [AiToolController::class, 'memoGenerator']);
            Route::post('/legal-notice-generator', [AiToolController::class, 'legalNoticeGenerator']);
            Route::post('/demand-letter-generator', [AiToolController::class, 'demandLetterGenerator']);
            Route::post('/timeline-generator', [AiToolController::class, 'timelineGenerator']);
            Route::post('/checklist-generator', [AiToolController::class, 'checklistGenerator']);
            Route::post('/client-explanation-simplifier', [AiToolController::class, 'clientExplanationSimplifier']);
            Route::post('/defense-assistant', [AiToolController::class, 'defenseAssistant']);
            Route::get('/history', [AiToolController::class, 'history']);
            Route::get('/{output}/download', [AiToolController::class, 'download']);
        });

        // Hearings / Calendar
        Route::apiResource('hearings', HearingController::class);
        Route::get('/calendar', [HearingController::class, 'calendar']);
        Route::get('/agenda', [HearingController::class, 'upcoming']);
        Route::get('/upcoming-hearings', [HearingController::class, 'upcoming']);

        // Tasks
        Route::apiResource('tasks', TaskController::class);
        Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);

        // Notes
        Route::apiResource('notes', NoteController::class);
        Route::post('/notes/{note}/pin', [NoteController::class, 'pin']);

        // Templates
        Route::get('/template-categories', [TemplateController::class, 'categories']);
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/templates/{template}', [TemplateController::class, 'show']);
        Route::post('/templates/{template}/favorite', [TemplateController::class, 'favorite']);
        Route::post('/templates/{template}/generate', [TemplateController::class, 'generate']);
        Route::get('/generated-documents/{document}/download', [TemplateController::class, 'download']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // Subscriptions
        Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
        Route::get('/subscription/current', [SubscriptionController::class, 'current']);
        Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/usage', [SubscriptionController::class, 'usage']);
        Route::get('/features', [SubscriptionController::class, 'features']);

        // Global Search
        Route::get('/search', [GlobalSearchController::class, 'search']);

        // Settings
        Route::get('/settings', [SettingController::class, 'getSettings']);
        Route::put('/settings', [SettingController::class, 'updateSettings']);
        Route::post('/data-export', [SettingController::class, 'requestDataExport']);
        Route::get('/legal-disclaimer', [SettingController::class, 'legalDisclaimer']);


    });
});
