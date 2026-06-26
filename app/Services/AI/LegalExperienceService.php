<?php

namespace App\Services\AI;

use App\Enums\AiToolType;
use App\Models\Conversation;
use Illuminate\Support\Arr;

class LegalExperienceService
{
    public function buildConversationPayload(
        Conversation $conversation,
        string $answer,
        string $scopeReason,
        array $sources = [],
        array $retrieval = [],
        string $language = 'en'
    ): array
    {
        $language = $this->normalizeLanguage($language);

        return [
            'answer' => $this->normalizeMarkdown($answer, $this->conversationFallbackTitle($conversation, $language)),
            'follow_up_questions' => $this->conversationFollowUps($conversation, $sources, $language),
            'next_question_prompt' => $language === 'ar'
                ? 'اسأل سؤالك القانوني التالي في أي وقت، وسأتابع من نفس السياق.'
                : 'Ask the next legal question anytime, and I will continue from here.',
            'presentation' => [
                'format' => 'markdown',
                'style' => 'judger_pro',
                'variant' => $conversation->isCase() ? 'case_guidance' : 'general_legal_guidance',
                'language' => $language,
                'direction' => $language === 'ar' ? 'rtl' : 'ltr',
                'show_sources' => ! empty($sources),
                'show_disclaimer' => true,
                'retrieval' => $retrieval,
            ],
            'scope' => [
                'allowed' => true,
                'reason' => $scopeReason,
            ],
            'retrieval' => $retrieval,
        ];
    }

    public function buildToolPayload(AiToolType $toolType, string $answer, array $sources = []): array
    {
        $isDraftingTool = in_array($toolType, [
            AiToolType::MemoGenerator,
            AiToolType::LegalNoticeGenerator,
            AiToolType::DemandLetterGenerator,
        ], true);

        return [
            'content' => $this->normalizeMarkdown($answer, $toolType->label()),
            'follow_up_questions' => $this->toolFollowUps($toolType),
            'next_question_prompt' => 'If you want another legal draft or revision, ask the next question and I will build on this output.',
            'presentation' => [
                'format' => 'markdown',
                'style' => 'judger_pro',
                'variant' => $isDraftingTool ? 'generated_legal_document' : 'legal_analysis',
                'show_sources' => ! empty($sources),
                'show_disclaimer' => true,
            ],
            'scope' => [
                'allowed' => true,
                'reason' => 'legal_tool_request',
            ],
        ];
    }

    public function buildNonLegalRedirectPayload(string $language = 'en'): array
    {
        $language = $this->normalizeLanguage($language);
        $message = $language === 'ar'
            ? config('ai.non_legal_redirect_message_ar')
            : config('ai.non_legal_redirect_message');

        return [
            'answer' => $this->normalizeMarkdown($message, $language === 'ar' ? 'الموضوعات القانونية فقط' : 'Legal Topics Only'),
            'follow_up_questions' => $language === 'ar'
                ? [
                    'هل يمكنك مراجعة عقد أو إنذار أو مستند محكمة؟',
                    'هل يمكنك شرح مهلة قانونية أو خطر أو إجراء؟',
                    'هل يمكنك صياغة مذكرة قانونية أو إنذار أو خطاب مطالبة أو قائمة تحقق؟',
                ]
                : [
                    'Can you review a contract, notice, or court document for me?',
                    'Can you explain a legal deadline, risk, or procedure?',
                    'Can you draft a legal memo, notice, demand letter, or checklist?',
                ],
            'next_question_prompt' => $language === 'ar'
                ? 'أعد صياغة طلبك كسؤال قانوني، وسأتابع مباشرة.'
                : 'Rephrase your request as a legal question, and I will continue right away.',
            'presentation' => [
                'format' => 'markdown',
                'style' => 'judger_pro',
                'variant' => 'legal_only_redirect',
                'language' => $language,
                'direction' => $language === 'ar' ? 'rtl' : 'ltr',
                'show_sources' => false,
                'show_disclaimer' => true,
            ],
            'scope' => [
                'allowed' => false,
                'reason' => 'non_legal_topic',
            ],
        ];
    }

    public function messageMetadata(array $payload, ?array $download = null): array
    {
        $metadata = Arr::only($payload, [
            'follow_up_questions',
            'next_question_prompt',
            'presentation',
            'scope',
            'retrieval',
        ]);

        if ($download !== null) {
            $metadata['download'] = $download;
        }

        return $metadata;
    }

    private function conversationFollowUps(Conversation $conversation, array $sources, string $language): array
    {
        if ($conversation->isCase()) {
            if ($language === 'ar') {
                return [
                    'ما المهلة أو الخطر أو الجلسة التي يجب متابعتها بعد ذلك؟',
                    'ما الدليل أو المستند الذي لا يزال ناقصا في هذه القضية؟',
                    'هل يمكنك تحويل ذلك إلى مذكرة أو جدول زمني أو قائمة تحقق؟',
                ];
            }

            return [
                'What deadline, risk, or hearing should I track next?',
                'What evidence or document is still missing in this case?',
                'Can you turn this into a memo, timeline, or checklist?',
            ];
        }

        if (! empty($sources)) {
            if ($language === 'ar') {
                return [
                    'هل يمكنك تبسيط ذلك بلغة أوضح؟',
                    'ما الوقائع التي قد تغير هذه الإجابة القانونية؟',
                    'ما السؤال التالي الذي يجب طرحه على محام بخصوص هذه المسألة؟',
                ];
            }

            return [
                'Can you simplify this in plain language?',
                'What facts could change this legal answer?',
                'What should I ask a lawyer next about this issue?',
            ];
        }

        if ($language === 'ar') {
            return [
                'هل يمكنك شرح ذلك بمثال عملي؟',
                'ما الوقائع التي تحتاجها مني لجعل الإجابة أكثر تحديدا؟',
                'ما السؤال التالي الذي يجب طرحه على محام بخصوص هذه المسألة؟',
            ];
        }

        return [
            'Can you explain this with a practical example?',
            'What facts do you need from me to make this more specific?',
            'What should I ask a lawyer next about this issue?',
        ];
    }

    private function toolFollowUps(AiToolType $toolType): array
    {
        return match ($toolType) {
            AiToolType::MemoGenerator => [
                'Should I rewrite this memo for a client-facing audience?',
                'Do you want a shorter executive summary version?',
                'Should I tailor this memo to a specific jurisdiction?',
            ],
            AiToolType::LegalNoticeGenerator, AiToolType::DemandLetterGenerator => [
                'Should I make the tone firmer or more neutral?',
                'Do you want deadlines or party details added next?',
                'Should I rewrite this for email or printed delivery?',
            ],
            default => [
                'Do you want this turned into a downloadable memo or checklist?',
                'Should I simplify this for a client or non-lawyer?',
                'Do you want a more detailed legal follow-up on one section?',
            ],
        };
    }

    private function normalizeMarkdown(string $content, string $fallbackTitle): string
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return "## {$fallbackTitle}";
        }

        if (preg_match('/^#{1,3}\s/u', $trimmed) === 1) {
            return $trimmed;
        }

        return "## {$fallbackTitle}\n\n{$trimmed}";
    }

    private function conversationFallbackTitle(Conversation $conversation, string $language): string
    {
        if ($language === 'ar') {
            return $conversation->isCase() ? 'إرشاد القضية' : 'إرشاد قانوني';
        }

        return $conversation->isCase() ? 'Case Guidance' : 'Legal Guidance';
    }

    private function normalizeLanguage(string $language): string
    {
        return $language === 'ar' ? 'ar' : 'en';
    }
}
