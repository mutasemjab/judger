<?php

namespace App\Jobs;

use App\Models\CaseMemory;
use App\Models\Message;
use App\Services\AI\AiProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateCaseMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private int $legalCaseId,
        private int $userId,
        private int $userMessageId,
        private int $assistantMessageId
    ) {}

    public function handle(): void
    {
        $userMessage = Message::find($this->userMessageId);
        $assistantMessage = Message::find($this->assistantMessageId);

        if (!$userMessage || !$assistantMessage) {
            return;
        }

        $provider = AiProviderManager::resolve();

        $prompt = <<<'PROMPT'
Extract important long-term facts about this legal case from the conversation.

Extract only useful case facts:
- parties, dates, deadlines, claims, defenses, evidence, risks, tasks, strategy notes, court details, jurisdiction details

Do not extract: temporary messages, guesses, unsupported legal conclusions, private irrelevant details

Return JSON only:
{
  "memories": [
    {
      "type": "fact|party|date|deadline|claim|defense|evidence|risk|task|strategy|general",
      "title": "short title",
      "content": "clear memory content",
      "confidence": 0.0
    }
  ]
}

Avoid duplicates.
PROMPT;

        try {
            $result = $provider->chatJson([
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => "User: {$userMessage->content}\n\nAssistant: {$assistantMessage->content}"],
            ]);

            $memories = $result['memories'] ?? [];
            $maxMemories = config('ai.max_case_memories', 20);
            $existing = CaseMemory::where('legal_case_id', $this->legalCaseId)
                ->where('user_id', $this->userId)
                ->count();

            foreach ($memories as $memory) {
                if ($existing >= $maxMemories) {
                    break;
                }

                $isDuplicate = CaseMemory::where('legal_case_id', $this->legalCaseId)
                    ->where('user_id', $this->userId)
                    ->where('title', $memory['title'] ?? '')
                    ->exists();

                if (!$isDuplicate && !empty($memory['content'])) {
                    CaseMemory::create([
                        'legal_case_id' => $this->legalCaseId,
                        'user_id' => $this->userId,
                        'type' => $memory['type'] ?? 'general',
                        'title' => $memory['title'] ?? null,
                        'content' => $memory['content'],
                        'confidence' => $memory['confidence'] ?? null,
                        'source' => 'chat',
                        'source_message_id' => $this->assistantMessageId,
                    ]);

                    $existing++;
                }
            }
        } catch (\Throwable) {
        }
    }
}
