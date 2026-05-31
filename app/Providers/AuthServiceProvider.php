<?php

namespace App\Providers;

use App\Models\CaseDocument;
use App\Models\Conversation;
use App\Models\Hearing;
use App\Models\KnowledgeDocument;
use App\Models\LegalCase;
use App\Models\Message;
use App\Models\Note;
use App\Models\Task;
use App\Policies\CaseDocumentPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\HearingPolicy;
use App\Policies\KnowledgeDocumentPolicy;
use App\Policies\LegalCasePolicy;
use App\Policies\MessagePolicy;
use App\Policies\NotePolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        LegalCase::class => LegalCasePolicy::class,
        CaseDocument::class => CaseDocumentPolicy::class,
        Conversation::class => ConversationPolicy::class,
        Message::class => MessagePolicy::class,
        Hearing::class => HearingPolicy::class,
        Task::class => TaskPolicy::class,
        Note::class => NotePolicy::class,
        KnowledgeDocument::class => KnowledgeDocumentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
