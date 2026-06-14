<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\Providers\MockLlmProvider;
use App\Services\AI\Providers\OpenAiProvider;
use InvalidArgumentException;

class AiProviderManager
{
    public static function resolve(): LlmProviderInterface
    {
        return self::makeProvider((string) config('ai.provider', 'mock'));
    }

    public static function resolveEmbedding(): LlmProviderInterface
    {
        $defaultProvider = (string) config('ai.provider', 'mock');
        $embeddingProvider = (string) (config('ai.embedding_provider') ?: $defaultProvider);

        return self::makeProvider($embeddingProvider);
    }

    private static function makeProvider(string $provider): LlmProviderInterface
    {
        return match ($provider) {
            'openai' => app(OpenAiProvider::class),
            'mock' => app(MockLlmProvider::class),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }
}
