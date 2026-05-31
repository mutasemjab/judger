<?php

namespace App\Services\Documents;

use App\Services\AI\AiProviderManager;

class DocumentAnalysisService
{
    public function analyze(string $text, string $documentType = 'document'): array
    {
        $provider = AiProviderManager::resolve();
        $disclaimer = config('ai.legal_disclaimer');

        $prompt = <<<PROMPT
Analyze this legal document and extract the following in JSON format:
{
  "summary": "brief document summary",
  "insights": ["key insight 1", "key insight 2"],
  "highlights": ["important highlight 1"],
  "detected_names": ["person or organization name"],
  "detected_dates": ["YYYY-MM-DD"],
  "detected_case_numbers": ["case number"],
  "detected_risks": ["risk description"],
  "missing_documents": ["possibly missing document"]
}

Document Type: {$documentType}

Document Text:
{$text}

Return ONLY valid JSON.
PROMPT;

        try {
            $result = $provider->chatJson([
                ['role' => 'system', 'content' => 'You are a legal document analyzer. Return only valid JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            return array_merge([
                'summary' => '',
                'insights' => [],
                'highlights' => [],
                'detected_names' => [],
                'detected_dates' => [],
                'detected_case_numbers' => [],
                'detected_risks' => [],
                'missing_documents' => [],
                'disclaimer' => $disclaimer,
            ], $result);
        } catch (\Throwable) {
            return [
                'summary' => 'Document analysis not available.',
                'insights' => [],
                'highlights' => [],
                'detected_names' => [],
                'detected_dates' => [],
                'detected_case_numbers' => [],
                'detected_risks' => [],
                'missing_documents' => [],
                'disclaimer' => $disclaimer,
            ];
        }
    }
}
