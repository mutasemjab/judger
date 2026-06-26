<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use Illuminate\Support\Str;

class LegalScopeGuard
{
    public function allowsConversationMessage(Conversation $conversation, string $message): array
    {
        if ($this->containsLegalSignal($message)) {
            return ['allowed' => true, 'reason' => 'legal_topic'];
        }

        $recentConversationText = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit(6)
            ->pluck('content')
            ->implode("\n");

        if ($recentConversationText !== ''
            && $this->containsLegalSignal($recentConversationText)
            && $this->looksLikeFollowUp($message)
        ) {
            return ['allowed' => true, 'reason' => 'legal_follow_up'];
        }

        return ['allowed' => false, 'reason' => 'non_legal_topic'];
    }

    private function containsLegalSignal(string $text): bool
    {
        $normalized = $this->normalize($text);

        if ($normalized === '') {
            return false;
        }

        foreach (config('ai.legal_keywords', []) as $keyword) {
            if ($this->containsPhrase($normalized, (string) $keyword)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/\b(legal|illegal|lawful|rights?|obligations?|liable|lawsuit|court|judge|contract|agreement|notice|appeal|evidence|deadline|compliance|regulation|policy|claim|damages?)\b/u',
            $normalized
        ) || (bool) preg_match(
            '/(قانون|قانوني|محام|محكمة|قضية|دعوى|عقد|اتفاقية|إنذار|إشعار|استئناف|دليل|أدلة|مهلة|اختصاص|امتثال|تعويض|غرامة|حقوق|التزامات|شكوى)/u',
            $normalized
        );
    }

    private function looksLikeFollowUp(string $text): bool
    {
        $normalized = $this->normalize($text);

        if ($normalized === '') {
            return false;
        }

        foreach (config('ai.follow_up_markers', []) as $marker) {
            if ($this->containsPhrase($normalized, (string) $marker)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/\b(this|that|it|they|those|these)\b.*\b(mean|change|help|affect|risk|deadline|document|case|contract|notice|letter|memo|hearing|filing)\b/u',
            $normalized
        ) || (bool) preg_match(
            '/^(can you|could you|would you|please)\s+(explain|simplify|rewrite|expand|continue|draft|summarize|turn)\b/u',
            $normalized
        ) || (bool) preg_match(
            '/^(what|how|why|when)\b.*\b(this|that|it|case|document|contract|notice|letter|memo|deadline|risk)\b/u',
            $normalized
        ) || (bool) preg_match(
            '/(هذا|هذه|ذلك|تلك|القضية|المستند|العقد|الإنذار|المذكرة|المهلة).*(يعني|يغير|يساعد|يؤثر|خطر|مخاطر|موعد|إجراء|أفعل|أعمل)/u',
            $normalized
        ) || (bool) preg_match(
            '/^(هل يمكنك|ممكن|من فضلك|اشرح|بسط|اختصر|أعد|اكتب|لخص|حوّل|تابع)/u',
            $normalized
        );
    }

    private function containsPhrase(string $normalizedText, string $keyword): bool
    {
        $normalizedKeyword = $this->normalize($keyword);

        if ($normalizedKeyword === '') {
            return false;
        }

        $pattern = '/(^|[^\pL\pN])' . preg_quote($normalizedKeyword, '/') . '($|[^\pL\pN])/u';

        return (bool) preg_match($pattern, $normalizedText);
    }

    private function normalize(string $text): string
    {
        $text = Str::lower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
