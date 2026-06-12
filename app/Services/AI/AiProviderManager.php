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
        if (app()->bound(LlmProviderInterface::class)) {
            return app(LlmProviderInterface::class);
        }

        $provider = config('ai.provider', 'mock');

        return match ($provider) {
            'openai' => new OpenAiProvider(),
            'mock' => new MockLlmProvider(),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }
}
