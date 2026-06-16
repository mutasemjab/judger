<?php

namespace App\Services\Chat;

use App\Enums\ConversationType;
use App\Enums\MessageRole;
use App\Enums\MessageSourceType;
use App\Jobs\SummarizeConversationJob;
use App\Jobs\UpdateCaseMemoryJob;
use App\Models\CaseMemory;
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
        private GeneratedFileExportService $exportService
    ) {}

    public function ask(int $userId, int $conversationId, string $message): array
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

        if ($conversation->type === ConversationType::Case) {
            return $this->handleCaseChat($conversation, $userMessage, $message, $userId);
        }

        return $this->handleGeneralChat($conversation, $userMessage, $message, $userId);
    }

    private function handleCaseChat(Conversation $conversation, Message $userMessage, string $message, int $userId): array
    {
        $legalCase = $conversation->legalCase;
        if (!$legalCase || $legalCase->user_id !== $userId) {
            throw new RuntimeException('Case not found or access denied.');
        }

        $scope = $this->scopeGuard->allowsConversationMessage($conversation, $message);
        if (! $scope['allowed']) {
            return $this->storeRedirectResponse(
                conversation: $conversation,
                legalCaseId: $legalCase->id,
                disclaimer: config('ai.legal_disclaimer')
            );
        }

        $caseSearchService = new CaseDocumentSearchService();
        $caseResults = $caseSearchService->search($userId, $legalCase->id, $message, config('ai.max_case_document_chunks'));

        $kbSearchService = new KnowledgeSearchService();
        $kbResults = $kbSearchService->search($message, config('ai.max_knowledge_chunks'));

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

        $contextParts = [];

        if (!empty($caseResults)) {
            $contextParts[] = "CASE DOCUMENT SOURCES:\n\n" . $this->formatCaseResults($caseResults);
        }

        $contextParts[] = "CASE CONTEXT:\n\n" .
            "Case title: {$legalCase->title}\n" .
            "Jurisdiction: " . ($legalCase->jurisdiction ?? 'Not specified') . "\n" .
            "Court: " . ($legalCase->court ?? 'Not specified') . "\n" .
            "Client: " . ($legalCase->client_name ?? 'Not specified') . "\n" .
            "Opposing party: " . ($legalCase->opposing_party ?? 'Not specified') . "\n" .
            "Summary: " . ($legalCase->summary ?? 'No summary yet') . "\n" .
            "Memories:\n" . $memories->map(fn($m) => "- [{$m->type?->value}] {$m->title}: {$m->content}")->join("\n");

        if (!empty($kbResults)) {
            $contextParts[] = "KNOWLEDGE BASE SOURCES:\n\n" . $this->formatKbResults($kbResults);
        }

        if ($recentMessages->isNotEmpty()) {
            $contextParts[] = "RECENT CONVERSATION:\n\n" . $recentMessages->map(fn($m) => ucfirst($m->role->value) . ': ' . $m->content)->join("\n\n");
        }

        $contextParts[] = "QUESTION:\n{$message}";
        $contextParts[] = "Answer using only the provided context. Cite source labels. Include the required legal disclaimer.";

        $fullContext = implode("\n\n---\n\n", $contextParts);

        $provider = AiProviderManager::resolve();
        $systemPrompt = config('ai.system_prompt');

        $answer = $provider->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $fullContext],
        ]);

        $disclaimer = config('ai.legal_disclaimer');
        if (!str_contains($answer, $disclaimer)) {
            $answer .= "\n\n" . $disclaimer;
        }

        $sourceType = $this->determineSourceType($caseResults, $kbResults);
        $sources = $this->buildSources($caseResults, $kbResults);
        $experience = $this->experience->buildConversationPayload($conversation, $answer, $scope['reason'], $sources);

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
        $assistantMessage->forceFill([
            'metadata' => $this->experience->messageMetadata($experience, $download),
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
            'download' => $this->exportService->publicDownloadData($download),
        ];
    }

    private function handleGeneralChat(Conversation $conversation, Message $userMessage, string $message, int $userId): array
    {
        if ($conversation->legal_case_id !== null) {
            throw new RuntimeException('General conversation must not be linked to a case.');
        }

        $scope = $this->scopeGuard->allowsConversationMessage($conversation, $message);
        if (! $scope['allowed']) {
            return $this->storeRedirectResponse(
                conversation: $conversation,
                legalCaseId: null,
                disclaimer: config('ai.legal_disclaimer')
            );
        }

        $kbSearchService = new KnowledgeSearchService();
        $kbResults = $kbSearchService->search($message, config('ai.max_knowledge_chunks'));

        $recentMessages = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', [MessageRole::User->value, MessageRole::Assistant->value])
            ->orderByDesc('created_at')
            ->limit(config('ai.recent_messages_limit', 12))
            ->get()
            ->reverse();

        $contextParts = [];

        if (!empty($kbResults)) {
            $contextParts[] = "KNOWLEDGE BASE SOURCES:\n\n" . $this->formatKbResults($kbResults);
        }

        if ($recentMessages->isNotEmpty()) {
            $contextParts[] = "RECENT CONVERSATION:\n\n" . $recentMessages->map(fn($m) => ucfirst($m->role->value) . ': ' . $m->content)->join("\n\n");
        }

        $contextParts[] = "QUESTION:\n{$message}";
        $contextParts[] = "Answer using only the provided context. Cite source labels. Include the required legal disclaimer.";

        $fullContext = implode("\n\n---\n\n", $contextParts);

        $provider = AiProviderManager::resolve();
        $systemPrompt = config('ai.system_prompt');

        $answer = $provider->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $fullContext],
        ]);

        $disclaimer = config('ai.legal_disclaimer');
        if (!str_contains($answer, $disclaimer)) {
            $answer .= "\n\n" . $disclaimer;
        }

        $sourceType = empty($kbResults) ? MessageSourceType::None : MessageSourceType::KnowledgeBase;
        $sources = $this->buildSources([], $kbResults);
        $experience = $this->experience->buildConversationPayload($conversation, $answer, $scope['reason'], $sources);

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
        $assistantMessage->forceFill([
            'metadata' => $this->experience->messageMetadata($experience, $download),
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
            'download' => $this->exportService->publicDownloadData($download),
        ];
    }

    private function storeRedirectResponse(Conversation $conversation, ?int $legalCaseId, string $disclaimer): array
    {
        $experience = $this->experience->buildNonLegalRedirectPayload();
        $answer = $experience['answer'];

        if (! str_contains($answer, $disclaimer)) {
            $answer .= "\n\n" . $disclaimer;
        }

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
        $assistantMessage->forceFill([
            'metadata' => $this->experience->messageMetadata($experience, $download),
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
        ];
    }

    private function formatCaseResults(array $results): string
    {
        $output = '';
        foreach (array_values($results) as $i => $result) {
            $n = $i + 1;
            $payload = $result['payload'] ?? [];
            $output .= "[CASE_SOURCE_{$n}]\n";
            $output .= "File: " . ($payload['document_name'] ?? 'Unknown') . "\n";
            $output .= "Document Type: " . ($payload['document_type'] ?? 'Unknown') . "\n";
            $output .= "Page: " . ($payload['page_number'] ?? '?') . "\n";
            $output .= "Chunk ID: case_" . ($payload['case_document_id'] ?? '?') . "_" . ($payload['chunk_index'] ?? '?') . "\n";
            $output .= "Text:\n" . ($payload['content'] ?? '') . "\n\n";
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
            $output .= "File: " . ($payload['document_name'] ?? 'Unknown') . "\n";
            $output .= "Category: " . ($payload['category'] ?? 'general') . "\n";
            $output .= "Page: " . ($payload['page_number'] ?? '?') . "\n";
            $output .= "Chunk ID: kb_" . ($payload['knowledge_document_id'] ?? '?') . "_" . ($payload['chunk_index'] ?? '?') . "\n";
            $output .= "Text:\n" . ($payload['content'] ?? '') . "\n\n";
        }
        return $output;
    }

    private function determineSourceType(array $caseResults, array $kbResults): MessageSourceType
    {
        if (!empty($caseResults) && !empty($kbResults)) {
            return MessageSourceType::Mixed;
        }
        if (!empty($caseResults)) {
            return MessageSourceType::CaseDocument;
        }
        if (!empty($kbResults)) {
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
                'chunk_id' => "case_" . ($p['case_document_id'] ?? '') . "_" . ($p['chunk_index'] ?? ''),
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
                'chunk_id' => "kb_" . ($p['knowledge_document_id'] ?? '') . "_" . ($p['chunk_index'] ?? ''),
                'similarity' => round($r['score'] ?? 0, 2),
                'snippet' => $p['snippet'] ?? '',
            ];
        }
        return $sources;
    }
}
