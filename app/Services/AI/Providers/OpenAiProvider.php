<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements LlmProviderInterface
{
    private string $apiKey;
    private string $chatModel;
    private string $embeddingModel;

    public function __construct()
    {
        $this->apiKey = config('ai.openai_api_key') ?? throw new RuntimeException('OpenAI API key is not configured.');
        $this->chatModel = config('ai.chat_model', 'gpt-4o-mini');
        $this->embeddingModel = config('ai.embedding_model', 'text-embedding-3-small');
    }

    public function chat(array $messages, array $options = []): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', array_merge([
                'model' => $options['model'] ?? $this->chatModel,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.3,
                'max_tokens' => $options['max_tokens'] ?? 2000,
            ], $options['extra'] ?? []));

        if ($response->failed()) {
            throw new RuntimeException('OpenAI chat request failed: ' . $response->status());
        }

        return $response->json('choices.0.message.content', '');
    }

    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $options['model'] ?? $this->chatModel,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI chatJson request failed: ' . $response->status());
        }

        $content = $response->json('choices.0.message.content', '{}');
        return json_decode($content, true) ?? [];
    }

    public function embedding(string $text): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->embeddingModel,
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embedding request failed: ' . $response->status());
        }

        return $response->json('data.0.embedding', []);
    }
}
