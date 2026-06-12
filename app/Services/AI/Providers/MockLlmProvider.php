<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProviderInterface;

class MockLlmProvider implements LlmProviderInterface
{
    public function chat(array $messages, array $options = []): string
    {
        $disclaimer = config('ai.legal_disclaimer');
        return "This is a mock AI response for testing purposes.\n\n{$disclaimer}";
    }

    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        return [
            'memories' => [
                [
                    'type' => 'general',
                    'title' => 'Mock memory',
                    'content' => 'This is a mock memory entry for testing.',
                    'confidence' => 0.85,
                ],
            ],
        ];
    }

    public function embedding(string $text): array
    {
        $dimensions = config('ai.embedding_dimensions', 1536);
        $vector = [];
        $hash = crc32($text);
        mt_srand($hash);
        for ($i = 0; $i < $dimensions; $i++) {
            $vector[] = (mt_rand(-1000, 1000)) / 1000.0;
        }
        return $vector;
    }

    public function embeddingMany(array $texts): array
    {
        return array_map(fn ($text): array => $this->embedding((string) $text), $texts);
    }
}
