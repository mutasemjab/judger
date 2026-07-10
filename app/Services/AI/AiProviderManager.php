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
        return self::makeProvider(self::configuredProvider());
    }

    public static function resolveEmbedding(): LlmProviderInterface
    {
        $defaultProvider = self::configuredProvider();
        $embeddingProvider = (string) (config('ai.embedding_provider') ?: $defaultProvider);

        return self::makeProvider($embeddingProvider === 'auto' ? $defaultProvider : $embeddingProvider);
    }

    private static function configuredProvider(): string
    {
        $provider = (string) config('ai.provider', 'auto');

        if ($provider !== 'auto') {
            return $provider;
        }

        return app(OpenAiConfigResolver::class)->apiKey() !== null ? 'openai' : 'mock';
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
