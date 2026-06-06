<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Web\AdminActivityLogWebController;
use App\Http\Controllers\Admin\Web\AdminAiToolsWebController;
use App\Http\Controllers\Admin\Web\AdminAuthController;
use App\Http\Controllers\Admin\Web\AdminCasesWebController;
use App\Http\Controllers\Admin\Web\AdminConversationWebController;
use App\Http\Controllers\Admin\Web\AdminDashboardWebController;
use App\Http\Controllers\Admin\Web\AdminDocumentsWebController;
use App\Http\Controllers\Admin\Web\AdminKnowledgeWebController;
use App\Http\Controllers\Admin\Web\AdminNotificationsWebController;
use App\Http\Controllers\Admin\Web\AdminPlansWebController;
use App\Http\Controllers\Admin\Web\AdminSubscriptionsWebController;
use App\Http\Controllers\Admin\Web\AdminTemplateCategoryWebController;
use App\Http\Controllers\Admin\Web\AdminTemplateWebController;
use App\Http\Controllers\Admin\Web\AdminUsersWebController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {

    // Redirect /admin → /admin/login
    Route::get('/', fn () => redirect()->route('admin.showlogin'));

    // ── Guest ────────────────────────────────────────────────────────────
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('showlogin');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login');

    // ── Authenticated ─────────────────────────────────────────────────────
    Route::middleware('admin_web')->group(function () {

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        // Profile
        Route::get('/profile', [AdminAuthController::class, 'profile'])->name('profile');
        Route::put('/profile', [AdminAuthController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/password', [AdminAuthController::class, 'updatePassword'])->name('profile.password');

        // Dashboard
        Route::get('/dashboard', [AdminDashboardWebController::class, 'index'])->name('dashboard');

        // Users
        Route::get('/users', [AdminUsersWebController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUsersWebController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/suspend', [AdminUsersWebController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/activate', [AdminUsersWebController::class, 'activate'])->name('users.activate');

        // Cases
        Route::get('/cases', [AdminCasesWebController::class, 'index'])->name('cases.index');

        // Case Documents
        Route::get('/documents', [AdminDocumentsWebController::class, 'index'])->name('documents.index');
        Route::post('/documents/{document}/reprocess', [AdminDocumentsWebController::class, 'reprocess'])->name('documents.reprocess');
        Route::delete('/documents/{document}', [AdminDocumentsWebController::class, 'destroy'])->name('documents.destroy');

        // Conversations
        Route::get('/conversations', [AdminConversationWebController::class, 'index'])->name('conversations.index');

        // Knowledge Documents
        Route::get('/knowledge', [AdminKnowledgeWebController::class, 'index'])->name('knowledge.index');
        Route::post('/knowledge', [AdminKnowledgeWebController::class, 'store'])->name('knowledge.store');
        Route::post('/knowledge/{knowledgeDocument}/reprocess', [AdminKnowledgeWebController::class, 'reprocess'])->name('knowledge.reprocess');
        Route::delete('/knowledge/{knowledgeDocument}', [AdminKnowledgeWebController::class, 'destroy'])->name('knowledge.destroy');

        // AI Tools
        Route::get('/ai-tools', [AdminAiToolsWebController::class, 'index'])->name('ai-tools.index');

        // Template Categories
        Route::get('/template-categories', [AdminTemplateCategoryWebController::class, 'index'])->name('template-categories.index');
        Route::post('/template-categories', [AdminTemplateCategoryWebController::class, 'store'])->name('template-categories.store');
        Route::put('/template-categories/{templateCategory}', [AdminTemplateCategoryWebController::class, 'update'])->name('template-categories.update');

        // Templates
        Route::get('/templates', [AdminTemplateWebController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [AdminTemplateWebController::class, 'create'])->name('templates.create');
        Route::post('/templates', [AdminTemplateWebController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}/edit', [AdminTemplateWebController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [AdminTemplateWebController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [AdminTemplateWebController::class, 'destroy'])->name('templates.destroy');

        // Subscription Plans
        Route::get('/plans', [AdminPlansWebController::class, 'index'])->name('plans.index');
        Route::put('/plans/{plan}', [AdminPlansWebController::class, 'update'])->name('plans.update');

        // Subscriptions
        Route::get('/subscriptions', [AdminSubscriptionsWebController::class, 'index'])->name('subscriptions.index');

        // Notifications
        Route::get('/notifications', [AdminNotificationsWebController::class, 'index'])->name('notifications.index');

        // Activity Logs
        Route::get('/activity-logs', [AdminActivityLogWebController::class, 'index'])->name('activity.index');

        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->name('role.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('role.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('role.store');
        Route::get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('role.edit');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->name('role.update');
        Route::delete('/roles', [RoleController::class, 'delete'])->name('role.delete');
    });
});
