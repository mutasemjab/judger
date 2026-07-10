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
        $answer = $this->normalizeMarkdown($answer, $this->conversationFallbackTitle($conversation, $language));
        $followUps = $this->conversationFollowUps($conversation, $sources, $language, $answer, $retrieval);

        return [
            'answer' => $answer,
            'follow_up_questions' => $followUps,
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
                'render_hints' => [
                    'show_markdown' => true,
                    'show_follow_up_chips' => true,
                    'show_download_button' => true,
                    'show_source_cards' => ! empty($sources),
                    'compact_paragraphs' => true,
                ],
                'status_cards' => $this->conversationStatusCards($conversation, $sources, $retrieval, $language),
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
        $language = $this->detectLanguage($answer);
        $isDraftingTool = in_array($toolType, [
            AiToolType::MemoGenerator,
            AiToolType::LegalNoticeGenerator,
            AiToolType::DemandLetterGenerator,
            AiToolType::TimelineGenerator,
            AiToolType::ChecklistGenerator,
        ], true);
        $content = $this->normalizeMarkdown($answer, $toolType->label());

        return [
            'content' => $content,
            'follow_up_questions' => $this->toolFollowUps($toolType, $language, $content),
            'next_question_prompt' => $language === 'ar'
                ? 'اطلب أي تعديل أو نسخة أخرى، وسأبني على هذا المخرج مباشرة.'
                : 'Ask for any revision or another version, and I will build on this output.',
            'presentation' => [
                'format' => 'markdown',
                'style' => 'judger_pro',
                'variant' => $isDraftingTool ? 'generated_legal_document' : 'legal_analysis',
                'language' => $language,
                'direction' => $language === 'ar' ? 'rtl' : 'ltr',
                'show_sources' => ! empty($sources),
                'show_disclaimer' => true,
                'render_hints' => [
                    'show_markdown' => true,
                    'show_follow_up_chips' => true,
                    'show_download_button' => true,
                    'show_source_cards' => ! empty($sources),
                    'compact_paragraphs' => true,
                ],
                'status_cards' => $this->toolStatusCards($toolType, $sources, $language),
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
                'render_hints' => [
                    'show_markdown' => true,
                    'show_follow_up_chips' => true,
                    'show_download_button' => false,
                    'show_source_cards' => false,
                    'compact_paragraphs' => true,
                ],
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

        $metadata['actions'] = $this->actionsForPayload($payload, $download);

        return $metadata;
    }

    public function actionsForPayload(array $payload, ?array $download = null): array
    {
        $language = $this->normalizeLanguage($payload['presentation']['language'] ?? 'en');
        $scopeAllowed = (bool) ($payload['scope']['allowed'] ?? true);

        if (! $scopeAllowed) {
            return [[
                'id' => 'ask_legal_question',
                'type' => 'prompt',
                'label' => $language === 'ar' ? 'اسأل سؤالا قانونيا' : 'Ask a legal question',
                'style' => 'primary',
            ]];
        }

        $actions = [];

        if (is_array($download) && ($download['available'] ?? false) && ! empty($download['url'])) {
            $actions[] = [
                'id' => 'download_docx',
                'type' => 'download',
                'label' => $language === 'ar' ? 'تنزيل ملف Word' : 'Download Word file',
                'style' => 'primary',
                'url' => $download['url'],
                'format' => $download['format'] ?? 'docx',
                'file_name' => $download['file_name'] ?? null,
            ];
        }

        $actions[] = [
            'id' => 'save_as_note',
            'type' => 'save',
            'label' => $language === 'ar' ? 'حفظ كملاحظة' : 'Save as note',
            'style' => 'secondary',
        ];

        $actions[] = [
            'id' => 'ask_follow_up',
            'type' => 'prompt',
            'label' => $language === 'ar' ? 'سؤال متابعة' : 'Ask follow-up',
            'style' => 'secondary',
        ];

        $retrieval = $payload['retrieval'] ?? [];
        $hasSources = (($retrieval['knowledge_base_results_count'] ?? 0) + ($retrieval['case_document_results_count'] ?? 0)) > 0
            || (bool) ($payload['presentation']['show_sources'] ?? false);

        if (! $hasSources) {
            $actions[] = [
                'id' => 'upload_source_document',
                'type' => 'upload',
                'label' => $language === 'ar' ? 'إضافة مستند داعم' : 'Add source document',
                'style' => 'tertiary',
            ];
        }

        return $actions;
    }

    private function conversationFollowUps(Conversation $conversation, array $sources, string $language, string $answer, array $retrieval): array
    {
        $questions = [];
        $hasSources = ! empty($sources);
        $answerLower = mb_strtolower($answer);

        if ($conversation->isCase()) {
            if ($language === 'ar') {
                $questions[] = 'ما الدليل أو المستند الذي لا يزال ناقصا في هذه القضية؟';
                $questions[] = 'ما المهلة أو الخطر أو الجلسة التي يجب متابعتها بعد ذلك؟';
                $questions[] = 'هل يمكنك تحويل ذلك إلى مذكرة أو جدول زمني أو قائمة تحقق؟';
                return $this->limitQuestions($questions);
            }

            $questions[] = 'What evidence or document is still missing in this case?';
            $questions[] = 'What deadline, risk, or hearing should I track next?';
            $questions[] = 'Can you turn this into a memo, timeline, or checklist?';
            return $this->limitQuestions($questions);
        }

        if ($language === 'ar') {
            if (! $hasSources) {
                $questions[] = 'هل لديك عقد أو مستند أو بلد اختصاص أضيفه لجعل الإجابة أدق؟';
            }

            if (str_contains($answerLower, 'مهلة') || str_contains($answerLower, 'موعد')) {
                $questions[] = 'ما التاريخ أو المهلة التي تريد تحويلها إلى خطوات عملية؟';
            }

            if (str_contains($answerLower, 'مخاطر') || str_contains($answerLower, 'خطر')) {
                $questions[] = 'هل تريد ترتيب المخاطر حسب الأولوية؟';
            }

            $questions[] = 'ما الوقائع التي قد تغير هذه الإجابة القانونية؟';
            $questions[] = 'ما السؤال التالي الذي يجب طرحه على محام بخصوص هذه المسألة؟';
            $questions[] = 'هل يمكنك تبسيط ذلك بلغة أوضح؟';

            return $this->limitQuestions($questions);
        }

        if (! $hasSources) {
            $questions[] = 'Do you have a contract, document, or jurisdiction I should use to make this more precise?';
        }

        if (str_contains($answerLower, 'deadline') || str_contains($answerLower, 'date')) {
            $questions[] = 'Which deadline or date should I turn into next steps?';
        }

        if (str_contains($answerLower, 'risk')) {
            $questions[] = 'Do you want the risks ranked by priority?';
        }

        $questions[] = 'What facts could change this legal answer?';
        $questions[] = 'What should I ask a lawyer next about this issue?';
        $questions[] = 'Can you simplify this in plain language?';

        return $this->limitQuestions($questions);
    }

    private function toolFollowUps(AiToolType $toolType, string $language, string $content): array
    {
        if ($language === 'ar') {
            return match ($toolType) {
                AiToolType::MemoGenerator => [
                    'هل تريد نسخة مختصرة كملخص تنفيذي؟',
                    'هل تريد صياغتها بلغة مناسبة للعميل؟',
                    'هل تريد تخصيصها لاختصاص قضائي محدد؟',
                ],
                AiToolType::LegalNoticeGenerator, AiToolType::DemandLetterGenerator => [
                    'هل تريد جعل النبرة أكثر حزما أو أكثر حيادا؟',
                    'هل تريد إضافة مهلة أو بيانات الأطراف؟',
                    'هل تريد نسخة مناسبة للبريد الإلكتروني أو للطباعة؟',
                ],
                default => [
                    'هل تريد تحويل ذلك إلى مذكرة أو قائمة تحقق قابلة للتنزيل؟',
                    'هل تريد تبسيطه للعميل أو لغير المتخصص؟',
                    'هل تريد تفصيلا قانونيا أكثر لأحد الأقسام؟',
                ],
            };
        }

        return match ($toolType) {
            AiToolType::MemoGenerator => [
                'Do you want a shorter executive summary version?',
                'Should I rewrite this memo for a client-facing audience?',
                'Should I tailor this memo to a specific jurisdiction?',
            ],
            AiToolType::LegalNoticeGenerator, AiToolType::DemandLetterGenerator => [
                'Should I make the tone firmer or more neutral?',
                'Do you want deadlines or party details added next?',
                'Should I rewrite this for email or printed delivery?',
            ],
            default => [
                str_contains(mb_strtolower($content), 'checklist')
                    ? 'Do you want the checklist reordered by urgency?'
                    : 'Do you want this turned into a memo or checklist?',
                'Should I simplify this for a client or non-lawyer?',
                'Do you want a more detailed legal follow-up on one section?',
            ],
        };
    }

    private function conversationStatusCards(Conversation $conversation, array $sources, array $retrieval, string $language): array
    {
        $sourceCount = count($sources);
        $cards = [[
            'id' => 'answer_type',
            'label' => $language === 'ar' ? 'نوع الرد' : 'Answer type',
            'value' => $conversation->isCase()
                ? ($language === 'ar' ? 'إرشاد قضية' : 'Case guidance')
                : ($language === 'ar' ? 'إرشاد قانوني عام' : 'General legal guidance'),
        ]];

        $cards[] = [
            'id' => 'sources',
            'label' => $language === 'ar' ? 'المصادر المستخدمة' : 'Sources used',
            'value' => $sourceCount > 0
                ? (string) $sourceCount
                : ($language === 'ar' ? 'لا توجد مطابقة قوية' : 'No strong match'),
        ];

        if (($retrieval['case_documents_searched'] ?? false) === true) {
            $cards[] = [
                'id' => 'case_documents',
                'label' => $language === 'ar' ? 'مستندات القضية' : 'Case documents',
                'value' => (string) ($retrieval['case_document_results_count'] ?? 0),
            ];
        }

        return $cards;
    }

    private function toolStatusCards(AiToolType $toolType, array $sources, string $language): array
    {
        return [
            [
                'id' => 'tool_type',
                'label' => $language === 'ar' ? 'نوع الأداة' : 'Tool type',
                'value' => $toolType->label(),
            ],
            [
                'id' => 'download_ready',
                'label' => $language === 'ar' ? 'جاهز للتنزيل' : 'Download ready',
                'value' => $language === 'ar' ? 'نعم' : 'Yes',
            ],
            [
                'id' => 'sources',
                'label' => $language === 'ar' ? 'المصادر المستخدمة' : 'Sources used',
                'value' => count($sources) > 0 ? (string) count($sources) : ($language === 'ar' ? 'لا توجد' : 'None'),
            ],
        ];
    }

    private function limitQuestions(array $questions): array
    {
        return array_slice(array_values(array_unique(array_filter($questions))), 0, 3);
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

    private function detectLanguage(string $text): string
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1 ? 'ar' : 'en';
    }
}
