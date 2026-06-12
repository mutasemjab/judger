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
        return $this->embeddingMany([$text])[0] ?? [];
    }

    public function embeddingMany(array $texts): array
    {
        $inputs = array_values(array_filter(array_map(
            fn ($text): string => trim((string) $text),
            $texts
        ), fn (string $text): bool => $text !== ''));

        if ($inputs === []) {
            return [];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout((int) config('ai.embedding_request_timeout', 90))
            ->retry(2, 1000)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->embeddingModel,
                'input' => $inputs,
            ]);

        if ($response->failed()) {
            $body = mb_substr((string) $response->body(), 0, 500);

            throw new RuntimeException('OpenAI embedding request failed: ' . $response->status() . ' ' . $body);
        }

        $data = $response->json('data', []);

        if (! is_array($data)) {
            throw new RuntimeException('OpenAI embedding response did not contain a valid data array.');
        }

        usort($data, fn (array $left, array $right): int => ((int) ($left['index'] ?? 0)) <=> ((int) ($right['index'] ?? 0)));

        return array_values(array_map(
            fn (array $item): array => is_array($item['embedding'] ?? null) ? $item['embedding'] : [],
            $data
        ));
    }
}
