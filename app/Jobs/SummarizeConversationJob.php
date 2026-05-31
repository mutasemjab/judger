<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AiProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SummarizeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private int $conversationId) {}

    public function handle(): void
    {
        $conversation = Conversation::find($this->conversationId);
        if (!$conversation) {
            return;
        }

        $messages = Message::where('conversation_id', $this->conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        if ($messages->count() < 4) {
            return;
        }

        $transcript = $messages->map(fn($m) => ucfirst($m->role->value) . ': ' . mb_substr($m->content, 0, 300))->join("\n\n");

        $provider = AiProviderManager::resolve();

        try {
            $summary = $provider->chat([
                ['role' => 'system', 'content' => 'Summarize this legal conversation in 2-3 sentences. Focus on the main legal topics discussed.'],
                ['role' => 'user', 'content' => $transcript],
            ]);

            $conversation->update(['summary' => $summary]);
        } catch (\Throwable) {
        }
    }
}
