<?php

namespace App\Services\AI\Providers;

use App\Services\AI\OpenAiConfigResolver;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OpenAiProvider implements LlmProviderInterface
{
    private string $apiKey;
    private string $chatModel;
    private string $embeddingModel;
    private ?string $organization;

    public function __construct(private OpenAiConfigResolver $configResolver)
    {
        $this->apiKey = $this->configResolver->apiKey()
            ?? throw new RuntimeException('OpenAI API key is not configured. Set OPENAI_API_KEY or save it from the knowledge admin screen.');

        $this->configResolver->syncIntoRuntimeConfig();
        $this->chatModel = config('ai.chat_model', 'gpt-4o-mini');
        $this->embeddingModel = config('ai.embedding_model', 'text-embedding-3-small');
        $this->organization = config('openai.organization');
    }

    public function chat(array $messages, array $options = []): string
    {
        $response = $this->http()
            ->connectTimeout(15)
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
        $response = $this->http()
            ->connectTimeout(15)
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

        // connectTimeout caps the TLS/TCP handshake itself.
        // Without it, a hung connection (firewall intercept, proxy stall) can
        // bypass the transfer timeout and hang the process indefinitely.
        $connectTimeout = (int) config('ai.embedding_connect_timeout', 15);
        $transferTimeout = (int) config('ai.embedding_request_timeout', 45);
        $startedAt = microtime(true);

        Log::info('ai.embedding.request_started', [
            'provider' => 'openai',
            'model' => $this->embeddingModel,
            'inputs_count' => count($inputs),
            'characters_total' => array_sum(array_map('mb_strlen', $inputs)),
            'connect_timeout_seconds' => $connectTimeout,
            'request_timeout_seconds' => $transferTimeout,
        ]);

        try {
            $response = $this->http()
                ->connectTimeout($connectTimeout)
                ->timeout($transferTimeout)
                ->retry(1, 500)   // one retry only - fast failure is better than 3x hang
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->embeddingModel,
                    'input' => $inputs,
                ]);
        } catch (Throwable $exception) {
            Log::error('ai.embedding.request_exception', [
                'provider' => 'openai',
                'model' => $this->embeddingModel,
                'inputs_count' => count($inputs),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::info('ai.embedding.request_completed', [
            'provider' => 'openai',
            'model' => $this->embeddingModel,
            'inputs_count' => count($inputs),
            'duration_ms' => $durationMs,
            'http_status' => $response->status(),
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

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::withToken($this->apiKey);

        if (is_string($this->organization) && trim($this->organization) !== '') {
            $request = $request->withHeaders([
                'OpenAI-Organization' => $this->organization,
            ]);
        }

        return $request;
    }
}
