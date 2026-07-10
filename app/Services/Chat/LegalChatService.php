<?php

namespace App\Services\Chat;

use App\Enums\ConversationType;
use App\Enums\MessageRole;
use App\Enums\MessageSourceType;
use App\Jobs\SummarizeConversationJob;
use App\Jobs\UpdateCaseMemoryJob;
use App\Models\CaseMemory;
use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AiProviderManager;
use App\Services\AI\LegalExperienceService;
use App\Services\Documents\GeneratedFileExportService;
use App\Services\Search\CaseDocumentSearchService;
use App\Services\Search\KnowledgeSearchService;
use RuntimeException;

class LegalChatService
{
    public function __construct(
        private LegalScopeGuard $scopeGuard,
        private LegalExperienceService $experience,
        private GeneratedFileExportService $exportService,
        private KnowledgeSearchService $knowledgeSearch,
        private CaseDocumentSearchService $caseDocumentSearch
    ) {
    }

    public function ask(int $userId, int $conversationId, string $message, array $attachmentIds = []): array
    {
        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $userMessage = Message::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'legal_case_id' => $conversation->legal_case_id,
            'role' => MessageRole::User->value,
            'content' => $message,
        ]);

        $attachments = $this->claimAttachments($conversation, $userMessage, $userId, $attachmentIds);

        if ($conversation->type === ConversationType::Case) {
            return $this->handleCaseChat($conversation, $userMessage, $message, $userId, $attachments);
        }

        return $this->handleGeneralChat($conversation, $userMessage, $message, $userId, $attachments);
    }

    private function handleCaseChat(Conversation $conversation, Message $userMessage, string $message, int $userId, $attachments): array
    {
        $legalCase = $conversation->legalCase;
        if (! $legalCase || $legalCase->user_id !== $userId) {
            throw new RuntimeException('Case not found or access denied.');
        }

        $language = $this->detectLanguage($message);
        $disclaimer = $this->disclaimerForLanguage($language);

        $scope = $this->scopeGuard->allowsConversationMessage($conversation, $message);
        $scopeReason = $this->scopeReasonForLlm($scope);

        if (! ($scope['allowed'] ?? false)) {
            return $this->storeRedirectResponse($conversation, $legalCase->id, $disclaimer, $language);
        }

        $embedding = AiProviderManager::resolveEmbedding()->embedding($message);
        $caseResults = $this->caseDocumentSearch->searchByEmbedding(
            $userId,
            $legalCase->id,
            $embedding,
            config('ai.max_case_document_chunks')
        );
        $kbResults = $this->knowledgeSearch->searchByEmbedding($embedding, config('ai.max_knowledge_chunks'));
        $retrieval = $this->buildRetrievalMetadata($caseResults, $kbResults, true);

        $memories = CaseMemory::where('legal_case_id', $legalCase->id)
            ->where('user_id', $userId)
            ->limit(config('ai.max_case_memories', 20))
            ->get();

        $recentMessages = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', [MessageRole::User->value, MessageRole::Assistant->value])
            ->orderByDesc('created_at')
            ->limit(config('ai.recent_messages_limit', 12))
            ->get()
            ->reverse();

        $contextParts = [
            $this->formatRetrievalStatus($retrieval),
        ];

        if (! empty($caseResults)) {
            $contextParts[] = "CASE DOCUMENT SOURCES:\n\n".$this->formatCaseResults($caseResults);
        }

        $contextParts[] = "CASE CONTEXT:\n\n".
            "Case title: {$legalCase->title}\n".
            'Jurisdiction: '.($legalCase->jurisdiction ?? 'Not specified')."\n".
            'Court: '.($legalCase->court ?? 'Not specified')."\n".
            'Client: '.($legalCase->client_name ?? 'Not specified')."\n".
            'Opposing party: '.($legalCase->opposing_party ?? 'Not specified')."\n".
            'Summary: '.($legalCase->summary ?? 'No summary yet')."\n".
            "Memories:\n".$memories->map(fn ($m) => "- [{$m->type?->value}] {$m->title}: {$m->content}")->join("\n");

        if (! empty($kbResults)) {
            $contextParts[] = "KNOWLEDGE BASE SOURCES:\n\n".$this->formatKbResults($kbResults);
        }

        if ($attachments->isNotEmpty()) {
            $contextParts[] = "USER ATTACHMENTS:\n\n".$this->formatAttachments($attachments);
        }

        if ($recentMessages->isNotEmpty()) {
            $contextParts[] = "RECENT CONVERSATION:\n\n".$recentMessages->map(fn ($m) => ucfirst($m->role->value).': '.$m->content)->join("\n\n");
        }

        $contextParts[] = "QUESTION:\n{$message}";
        $contextParts[] = $this->buildAnswerInstructions($language, true, $disclaimer);

        $fullContext = implode("\n\n---\n\n", $contextParts);

        $provider = AiProviderManager::resolve();
        $systemPrompt = config('ai.system_prompt');

        $answer = $provider->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $fullContext],
        ], [
            'temperature' => (float) config('ai.chat_temperature', 0.25),
            'max_tokens' => (int) config('ai.chat_max_tokens', 1600),
        ]);

        $answer = $this->removeDisclaimerFromAnswer($answer, $disclaimer);

        $sourceType = $this->determineSourceType($caseResults, $kbResults);
        $sources = $this->buildSources($caseResults, $kbResults);
        $experience = $this->experience->buildConversationPayload(
            $conversation,
            $answer,
            $scopeReason,
            $sources,
            $retrieval,
            $language
        );

        $assistantMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'legal_case_id' => $legalCase->id,
            'role' => MessageRole::Assistant->value,
            'content' => $experience['answer'],
            'source_type' => $sourceType->value,
            'sources' => $sources,
            'disclaimer' => $disclaimer,
        ]);

        $download = $this->exportService->exportMessage($assistantMessage);
        $metadata = $this->experience->messageMetadata($experience, $download);
        $assistantMessage->forceFill([
            'metadata' => $metadata,
        ])->save();

        $conversation->update(['last_message_at' => now()]);

        UpdateCaseMemoryJob::dispatch($legalCase->id, $userId, $userMessage->id, $assistantMessage->id);

        if ($recentMessages->count() >= 10) {
            SummarizeConversationJob::dispatch($conversation->id);
        }

        return [
            'answer' => $experience['answer'],
            'disclaimer' => $disclaimer,
            'source_type' => $sourceType->value,
            'sources' => $sources,
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'follow_up_questions' => $experience['follow_up_questions'],
            'next_question_prompt' => $experience['next_question_prompt'],
            'presentation' => $experience['presentation'],
            'scope' => $experience['scope'],
            'retrieval' => $experience['retrieval'],
            'download' => $this->exportService->publicDownloadData($download),
            'download_url' => $download['url'] ?? null,
            'actions' => $metadata['actions'] ?? [],
        ];
    }

    private function handleGeneralChat(Conversation $conversation, Message $userMessage, string $message, int $userId, $attachments): array
    {
        if ($conversation->legal_case_id !== null) {
            throw new RuntimeException('General conversation must not be linked to a case.');
        }

        $language = $this->detectLanguage($message);
        $disclaimer = $this->disclaimerForLanguage($language);

        $scope = $this->scopeGuard->allowsConversationMessage($conversation, $message);
        $scopeReason = $this->scopeReasonForLlm($scope);

        if (! ($scope['allowed'] ?? false)) {
            return $this->storeRedirectResponse($conversation, null, $disclaimer, $language);
        }

        $kbResults = $this->knowledgeSearch->search($message, config('ai.max_knowledge_chunks'));
        $retrieval = $this->buildRetrievalMetadata([], $kbResults, false);

        $recentMessages = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', [MessageRole::User->value, MessageRole::Assistant->value])
            ->orderByDesc('created_at')
            ->limit(config('ai.recent_messages_limit', 12))
            ->get()
            ->reverse();

        $contextParts = [
            $this->formatRetrievalStatus($retrieval),
        ];

        if (! empty($kbResults)) {
            $contextParts[] = "KNOWLEDGE BASE SOURCES:\n\n".$this->formatKbResults($kbResults);
        }

        if ($attachments->isNotEmpty()) {
            $contextParts[] = "USER ATTACHMENTS:\n\n".$this->formatAttachments($attachments);
        }

        if ($recentMessages->isNotEmpty()) {
            $contextParts[] = "RECENT CONVERSATION:\n\n".$recentMessages->map(fn ($m) => ucfirst($m->role->value).': '.$m->content)->join("\n\n");
        }

        $contextParts[] = "QUESTION:\n{$message}";
        $contextParts[] = $this->buildAnswerInstructions($language, false, $disclaimer);

        $fullContext = implode("\n\n---\n\n", $contextParts);

        $provider = AiProviderManager::resolve();
        $systemPrompt = config('ai.system_prompt');

        $answer = $provider->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $fullContext],
        ], [
            'temperature' => (float) config('ai.chat_temperature', 0.25),
            'max_tokens' => (int) config('ai.chat_max_tokens', 1600),
        ]);

        $answer = $this->removeDisclaimerFromAnswer($answer, $disclaimer);

        $sourceType = empty($kbResults) ? MessageSourceType::None : MessageSourceType::KnowledgeBase;
        $sources = $this->buildSources([], $kbResults);
        $experience = $this->experience->buildConversationPayload(
            $conversation,
            $answer,
            $scopeReason,
            $sources,
            $retrieval,
            $language
        );

        $assistantMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'legal_case_id' => null,
            'role' => MessageRole::Assistant->value,
            'content' => $experience['answer'],
            'source_type' => $sourceType->value,
            'sources' => $sources,
            'disclaimer' => $disclaimer,
        ]);

        $download = $this->exportService->exportMessage($assistantMessage);
        $metadata = $this->experience->messageMetadata($experience, $download);
        $assistantMessage->forceFill([
            'metadata' => $metadata,
        ])->save();

        $conversation->update(['last_message_at' => now()]);

        return [
            'answer' => $experience['answer'],
            'disclaimer' => $disclaimer,
            'source_type' => $sourceType->value,
            'sources' => $sources,
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'follow_up_questions' => $experience['follow_up_questions'],
            'next_question_prompt' => $experience['next_question_prompt'],
            'presentation' => $experience['presentation'],
            'scope' => $experience['scope'],
            'retrieval' => $experience['retrieval'],
            'download' => $this->exportService->publicDownloadData($download),
            'download_url' => $download['url'] ?? null,
            'actions' => $metadata['actions'] ?? [],
        ];
    }

    private function storeRedirectResponse(Conversation $conversation, ?int $legalCaseId, string $disclaimer, string $language): array
    {
        $experience = $this->experience->buildNonLegalRedirectPayload($language);
        $answer = $experience['answer'];

        $answer = $this->removeDisclaimerFromAnswer($answer, $disclaimer);

        $assistantMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'legal_case_id' => $legalCaseId,
            'role' => MessageRole::Assistant->value,
            'content' => $answer,
            'source_type' => MessageSourceType::None->value,
            'sources' => [],
            'disclaimer' => $disclaimer,
        ]);

        $download = $this->exportService->exportMessage($assistantMessage);
        $metadata = $this->experience->messageMetadata($experience, $download);
        $assistantMessage->forceFill([
            'metadata' => $metadata,
        ])->save();

        $conversation->update(['last_message_at' => now()]);

        return [
            'answer' => $answer,
            'disclaimer' => $disclaimer,
            'source_type' => MessageSourceType::None->value,
            'sources' => [],
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'follow_up_questions' => $experience['follow_up_questions'],
            'next_question_prompt' => $experience['next_question_prompt'],
            'presentation' => $experience['presentation'],
            'scope' => $experience['scope'],
            'download' => $this->exportService->publicDownloadData($download),
            'download_url' => $download['url'] ?? null,
            'actions' => $metadata['actions'] ?? [],
        ];
    }

    private function detectLanguage(string $text): string
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1 ? 'ar' : 'en';
    }

    private function disclaimerForLanguage(string $language): string
    {
        return $language === 'ar'
            ? (string) config('ai.legal_disclaimer_ar')
            : (string) config('ai.legal_disclaimer');
    }

    private function scopeReasonForLlm(array $scope): string
    {
        return ($scope['allowed'] ?? false)
            ? (string) ($scope['reason'] ?? 'legal_topic')
            : 'llm_scope_check';
    }

    private function buildRetrievalMetadata(array $caseResults, array $kbResults, bool $caseChat): array
    {
        return [
            'strategy' => $caseChat
                ? 'case_documents_and_knowledge_base_before_llm'
                : 'knowledge_base_before_llm',
            'knowledge_base_searched' => true,
            'knowledge_base_searched_before_llm' => true,
            'knowledge_base_results_count' => count($kbResults),
            'case_documents_searched' => $caseChat,
            'case_document_results_count' => count($caseResults),
            'llm_context_source' => 'retrieved_context',
        ];
    }

    private function formatRetrievalStatus(array $retrieval): string
    {
        return "RETRIEVAL STATUS:\n"
            .'- Knowledge base searched before LLM: '.($retrieval['knowledge_base_searched_before_llm'] ? 'yes' : 'no')."\n"
            .'- Knowledge base source chunks: '.$retrieval['knowledge_base_results_count']."\n"
            .'- Case documents searched: '.($retrieval['case_documents_searched'] ? 'yes' : 'no')."\n"
            .'- Case document source chunks: '.$retrieval['case_document_results_count']."\n"
            .'- Context rule: use retrieved sources first; if no strong source match is listed, say that clearly before any cautious general orientation.';
    }

    private function buildAnswerInstructions(string $language, bool $caseChat, string $disclaimer): string
    {
        if ($language === 'ar') {
            $sourceRule = $caseChat
                ? 'بالنسبة لوقائع القضية، استخدم مصادر مستندات القضية أولا. وبالنسبة للقواعد القانونية العامة، استخدم مصادر قاعدة المعرفة قبل المعرفة العامة للنموذج.'
                : 'استخدم مصادر قاعدة المعرفة قبل المعرفة العامة للنموذج.';

            return "RESPONSE INSTRUCTIONS:\n"
                ."- اكتب الإجابة بالعربية الفصحى الواضحة، إلا إذا طلب المستخدم الإنجليزية صراحة.\n"
                ."- استخدم Markdown منظم وجذاب بعناوين قصيرة: `## الخلاصة السريعة`، `## ما تقوله المصادر`، `## الخطوات العملية`، و`## ما الذي يجب توضيحه بعد ذلك`.\n"
                ."- اجعل الفقرات قصيرة وسهلة القراءة، واستخدم النقاط أو الجداول عندما تجعل الإجابة أسرع في الفهم.\n"
                ."- لا تعتمد على فلترة التطبيق لتحديد النطاق القانوني. أنت من يقرر من النص نفسه هل الطلب قانوني أم لا.\n"
                ."- إذا كان الطلب غير قانوني فعلا، ارفض بلطف واطلب سؤالا قانونيا بدلا منه. إذا كان السؤال يمكن فهمه كقانوني، أجب عليه.\n"
                ."- {$sourceRule}\n"
                ."- اذكر مصدر كل نقطة مأخوذة من المستندات بصيغة [KB_SOURCE_1] أو [CASE_SOURCE_1].\n"
                ."- إذا لم تظهر مصادر كافية، قل بوضوح إن البحث في قاعدة المعرفة لم يرجع مطابقات قوية.\n"
                ."- لا تخترع قوانين أو مواعيد أو وقائع قضية غير موجودة في السياق.\n"
                ."- إذا طلب المستخدم إنشاء ملف أو تنزيل مذكرة أو إنذار أو خطاب أو قائمة تحقق أو جدول زمني، اكتب المحتوى كاملا بصيغة جاهزة للتصدير ولا تقل إنك لا تستطيع إنشاء ملف؛ سيضيف النظام ملف Word قابل للتنزيل.\n"
                ."- إذا كانت بعض البيانات ناقصة في المستند، استخدم خانات واضحة مثل [اسم الطرف] أو [التاريخ] بدلا من إيقاف الإجابة بالكامل.\n"
                ."- اختم بسؤال متابعة قانوني واحد يساعد المستخدم على المتابعة.\n"
                ."- لا تضع نص إخلاء المسؤولية داخل الإجابة نفسها؛ سيعرض التطبيق هذا النص مرة واحدة في حقل مستقل: {$disclaimer}";
        }

        $sourceRule = $caseChat
            ? 'For case-specific facts, use case document sources first. For general legal rules, use knowledge base sources before model background knowledge.'
            : 'Use knowledge base sources before model background knowledge.';

        return "RESPONSE INSTRUCTIONS:\n"
            ."- Answer in clear, polished English unless the user explicitly asks for Arabic.\n"
            ."- Use attractive, scannable Markdown with these short sections: `## Quick Answer`, `## What The Sources Say`, `## Practical Next Steps`, and `## What To Clarify Next`.\n"
            ."- Keep paragraphs short. Use bullets or compact tables when they make the answer faster to understand.\n"
            ."- Do not rely on application-side keyword filtering to decide legal scope. You must decide from the user's actual message.\n"
            ."- If the request is truly non-legal, politely refuse and invite a legal question. If it can reasonably be understood as legal, answer it.\n"
            ."- {$sourceRule}\n"
            ."- Cite every source-based point inline as [KB_SOURCE_1] or [CASE_SOURCE_1].\n"
            ."- If there are not enough source matches, clearly say the knowledge base did not return a strong match before any cautious general orientation.\n"
            ."- Do not invent laws, deadlines, citations, or case facts.\n"
            ."- If the user asks to create a file or download a memo, notice, letter, checklist, or timeline, write the complete export-ready content and do not say you cannot create a file; the application will attach a downloadable Word document.\n"
            ."- If details are missing from a document, use clear placeholders such as [party name] or [date] instead of stopping the answer entirely.\n"
            ."- End with one useful legal follow-up question.\n"
            ."- Do not include the disclaimer inside the answer body; the application will display it once from this separate field: {$disclaimer}";
    }

    private function removeDisclaimerFromAnswer(string $answer, string $disclaimer): string
    {
        $cleaned = trim(str_replace($disclaimer, '', $answer));
        $cleaned = preg_replace("/(\R\s*){3,}/u", "\n\n", $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    private function claimAttachments(Conversation $conversation, Message $message, int $userId, array $attachmentIds)
    {
        $ids = collect($attachmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $attachments = ChatAttachment::whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('conversation_id', $conversation->id)
            ->whereNull('message_id')
            ->get();

        $attachments->each(function (ChatAttachment $attachment) use ($message): void {
            $attachment->forceFill(['message_id' => $message->id])->save();
        });
        $message->load('attachments');

        return $attachments;
    }

    private function formatAttachments($attachments): string
    {
        return $attachments->values()->map(function (ChatAttachment $attachment, int $index): string {
            $n = $index + 1;
            $text = trim((string) $attachment->extracted_text);

            if ($text === '') {
                $text = $attachment->isImage()
                    ? 'Image attachment. Use the file name and user question; visual analysis is not available in this text model.'
                    : 'No readable text was extracted from this file.';
            }

            return "[ATTACHMENT_{$n}]\n"
                ."File: {$attachment->original_name}\n"
                .'Type: '.($attachment->mime_type ?: 'unknown')."\n"
                ."Content:\n{$text}";
        })->join("\n\n");
    }

    private function formatCaseResults(array $results): string
    {
        $output = '';
        foreach (array_values($results) as $i => $result) {
            $n = $i + 1;
            $payload = $result['payload'] ?? [];
            $output .= "[CASE_SOURCE_{$n}]\n";
            $output .= 'File: '.($payload['document_name'] ?? 'Unknown')."\n";
            $output .= 'Document Type: '.($payload['document_type'] ?? 'Unknown')."\n";
            $output .= 'Page: '.($payload['page_number'] ?? '?')."\n";
            $output .= 'Chunk ID: case_'.($payload['case_document_id'] ?? '?').'_'.($payload['chunk_index'] ?? '?')."\n";
            $output .= "Text:\n".($payload['content'] ?? '')."\n\n";
        }

        return $output;
    }

    private function formatKbResults(array $results): string
    {
        $output = '';
        foreach (array_values($results) as $i => $result) {
            $n = $i + 1;
            $payload = $result['payload'] ?? [];
            $output .= "[KB_SOURCE_{$n}]\n";
            $output .= 'File: '.($payload['document_name'] ?? 'Unknown')."\n";
            $output .= 'Category: '.($payload['category'] ?? 'general')."\n";
            $output .= 'Page: '.($payload['page_number'] ?? '?')."\n";
            $output .= 'Chunk ID: kb_'.($payload['knowledge_document_id'] ?? '?').'_'.($payload['chunk_index'] ?? '?')."\n";
            $output .= "Text:\n".($payload['content'] ?? '')."\n\n";
        }

        return $output;
    }

    private function determineSourceType(array $caseResults, array $kbResults): MessageSourceType
    {
        if (! empty($caseResults) && ! empty($kbResults)) {
            return MessageSourceType::Mixed;
        }
        if (! empty($caseResults)) {
            return MessageSourceType::CaseDocument;
        }
        if (! empty($kbResults)) {
            return MessageSourceType::KnowledgeBase;
        }

        return MessageSourceType::None;
    }

    private function buildSources(array $caseResults, array $kbResults): array
    {
        $sources = [];
        foreach (array_values($caseResults) as $i => $r) {
            $n = $i + 1;
            $p = $r['payload'] ?? [];
            $sources[] = [
                'source_label' => "CASE_SOURCE_{$n}",
                'source_type' => 'case_document',
                'file_name' => $p['document_name'] ?? '',
                'page_number' => $p['page_number'] ?? null,
                'chunk_id' => 'case_'.($p['case_document_id'] ?? '').'_'.($p['chunk_index'] ?? ''),
                'similarity' => round($r['score'] ?? 0, 2),
                'snippet' => $p['snippet'] ?? '',
            ];
        }
        foreach (array_values($kbResults) as $i => $r) {
            $n = $i + 1;
            $p = $r['payload'] ?? [];
            $sources[] = [
                'source_label' => "KB_SOURCE_{$n}",
                'source_type' => 'knowledge_base',
                'file_name' => $p['document_name'] ?? '',
                'page_number' => $p['page_number'] ?? null,
                'chunk_id' => 'kb_'.($p['knowledge_document_id'] ?? '').'_'.($p['chunk_index'] ?? ''),
                'similarity' => round($r['score'] ?? 0, 2),
                'snippet' => $p['snippet'] ?? '',
            ];
        }

        return $sources;
    }
}
