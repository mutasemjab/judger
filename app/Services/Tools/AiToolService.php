<?php

namespace App\Services\Tools;

use App\Enums\AiToolType;
use App\Models\AiToolOutput;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\AI\AiProviderManager;
use App\Services\Search\CaseDocumentSearchService;
use App\Services\Search\KnowledgeSearchService;

class AiToolService
{
    private string $disclaimer;

    public function __construct()
    {
        $this->disclaimer = config('ai.legal_disclaimer');
    }

    public function run(AiToolType $toolType, int $userId, array $input): array
    {
        $context = $this->buildContext($input, $userId);
        $prompt = $this->buildPrompt($toolType, $input, $context['text']);

        $provider = AiProviderManager::resolve();
        $answer = $provider->chat([
            ['role' => 'system', 'content' => 'You are Judger AI, a legal information assistant. ' . $this->disclaimer],
            ['role' => 'user', 'content' => $prompt],
        ]);

        if (!str_contains($answer, $this->disclaimer)) {
            $answer .= "\n\n" . $this->disclaimer;
        }

        $output = AiToolOutput::create([
            'user_id' => $userId,
            'legal_case_id' => $input['legal_case_id'] ?? null,
            'case_document_id' => $input['case_document_id'] ?? null,
            'tool_type' => $toolType->value,
            'input' => $input,
            'content' => $answer,
            'output' => ['result' => $answer],
            'disclaimer' => $this->disclaimer,
            'source_type' => $context['source_type'],
            'sources' => $context['sources'],
        ]);

        return [
            'id' => $output->id,
            'tool_type' => $toolType->value,
            'content' => $answer,
            'disclaimer' => $this->disclaimer,
            'sources' => $context['sources'],
            'source_type' => $context['source_type'],
        ];
    }

    private function buildContext(array $input, int $userId): array
    {
        $contextText = '';
        $sources = [];
        $sourceType = 'none';

        if (!empty($input['legal_case_id']) && !empty($input['query'])) {
            $caseSearch = new CaseDocumentSearchService();
            $caseResults = $caseSearch->search($userId, $input['legal_case_id'], $input['query']);

            if (!empty($caseResults)) {
                $contextText .= "CASE DOCUMENT SOURCES:\n\n";
                foreach (array_values($caseResults) as $i => $r) {
                    $n = $i + 1;
                    $p = $r['payload'] ?? [];
                    $contextText .= "[CASE_SOURCE_{$n}]\n{$p['content']}\n\n";
                    $sources[] = ['label' => "CASE_SOURCE_{$n}", 'type' => 'case_document', 'file' => $p['document_name'] ?? ''];
                }
                $sourceType = 'case_document';
            }

            $legalCase = LegalCase::find($input['legal_case_id']);
            if ($legalCase) {
                $contextText .= "CASE CONTEXT:\nTitle: {$legalCase->title}\nJurisdiction: " . ($legalCase->jurisdiction ?? 'N/A') . "\nSummary: " . ($legalCase->summary ?? 'N/A') . "\n\n";
            }
        }

        $kbSearch = new KnowledgeSearchService();
        $kbResults = $kbSearch->search($input['query'] ?? ($input['text'] ?? ''));

        if (!empty($kbResults)) {
            $contextText .= "KNOWLEDGE BASE SOURCES:\n\n";
            foreach (array_values($kbResults) as $i => $r) {
                $n = $i + 1;
                $p = $r['payload'] ?? [];
                $contextText .= "[KB_SOURCE_{$n}]\n{$p['content']}\n\n";
                $sources[] = ['label' => "KB_SOURCE_{$n}", 'type' => 'knowledge_base', 'file' => $p['document_name'] ?? ''];
            }
            $sourceType = $sourceType === 'case_document' ? 'mixed' : 'knowledge_base';
        }

        return [
            'text' => $contextText,
            'sources' => $sources,
            'source_type' => $sourceType,
        ];
    }

    private function buildPrompt(AiToolType $toolType, array $input, string $context): string
    {
        $label = $toolType->label();

        $contextBlock = $context ? "CONTEXT:\n{$context}\n\n" : '';

        $inputText = $input['text'] ?? $input['query'] ?? '';
        $caseDetails = '';

        if (!empty($input['additional_info'])) {
            $caseDetails = "Additional information: " . $input['additional_info'] . "\n";
        }

        return match ($toolType) {
            AiToolType::CaseSummarizer => "{$contextBlock}Summarize this legal case clearly and concisely:\n{$inputText}\n{$caseDetails}\nProvide a structured summary with key facts, parties, issues, and current status.",

            AiToolType::DocumentSummarizer => "{$contextBlock}Summarize this legal document:\n{$inputText}\nProvide: 1) Main purpose 2) Key provisions 3) Important dates/deadlines 4) Parties involved 5) Potential issues.",

            AiToolType::ContractAnalyzer => "{$contextBlock}Analyze this contract:\n{$inputText}\nProvide: 1) Contract type 2) Key obligations 3) Rights and duties 4) Risk clauses 5) Unusual or concerning provisions 6) Missing standard clauses.",

            AiToolType::RiskEstimator => "{$contextBlock}Estimate legal risks for:\n{$inputText}\n{$caseDetails}\nProvide: 1) Identified risks (High/Medium/Low) 2) Potential consequences 3) Risk mitigation suggestions. Note jurisdiction-specific variations may apply.",

            AiToolType::MemoGenerator => "{$contextBlock}Generate a legal memorandum for:\n{$inputText}\n{$caseDetails}\nInclude: To, From, Date, Re, Facts, Issue, Analysis, Conclusion.",

            AiToolType::LegalNoticeGenerator => "{$contextBlock}Generate a legal notice for:\n{$inputText}\n{$caseDetails}\nInclude: Date, Parties, Subject, Demand, Response deadline, Consequences of non-compliance.",

            AiToolType::DemandLetterGenerator => "{$contextBlock}Generate a demand letter for:\n{$inputText}\n{$caseDetails}\nInclude: Party details, factual background, legal basis, specific demand, deadline, next steps.",

            AiToolType::TimelineGenerator => "{$contextBlock}Create a legal timeline for:\n{$inputText}\n{$caseDetails}\nList events chronologically with dates, descriptions, and legal significance.",

            AiToolType::ChecklistGenerator => "{$contextBlock}Generate a legal checklist for:\n{$inputText}\n{$caseDetails}\nProvide actionable items organized by priority and category.",

            AiToolType::ClientExplanationSimplifier => "{$contextBlock}Explain this legal matter in plain language for a non-lawyer:\n{$inputText}\nAvoid jargon. Explain what it means, what action may be needed, and what questions to ask a lawyer.",

            AiToolType::DefenseAssistant => "{$contextBlock}Analyze defense strategy for:\n{$inputText}\n{$caseDetails}\nProvide: 1) Possible defenses 2) Weaknesses in the opposing claim 3) Evidence to gather 4) Key legal arguments. This is informational only - consult a qualified lawyer.",
        };
    }
}
