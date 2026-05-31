<?php

namespace App\Enums;

enum AiToolType: string
{
    case CaseSummarizer = 'case_summarizer';
    case DocumentSummarizer = 'document_summarizer';
    case ContractAnalyzer = 'contract_analyzer';
    case RiskEstimator = 'risk_estimator';
    case MemoGenerator = 'memo_generator';
    case LegalNoticeGenerator = 'legal_notice_generator';
    case DemandLetterGenerator = 'demand_letter_generator';
    case TimelineGenerator = 'timeline_generator';
    case ChecklistGenerator = 'checklist_generator';
    case ClientExplanationSimplifier = 'client_explanation_simplifier';
    case DefenseAssistant = 'defense_assistant';

    public function label(): string
    {
        return match($this) {
            self::CaseSummarizer => 'Case Summarizer',
            self::DocumentSummarizer => 'Document Summarizer',
            self::ContractAnalyzer => 'Contract Analyzer',
            self::RiskEstimator => 'Risk Estimator',
            self::MemoGenerator => 'Memo Generator',
            self::LegalNoticeGenerator => 'Legal Notice Generator',
            self::DemandLetterGenerator => 'Demand Letter Generator',
            self::TimelineGenerator => 'Timeline Generator',
            self::ChecklistGenerator => 'Checklist Generator',
            self::ClientExplanationSimplifier => 'Client Explanation Simplifier',
            self::DefenseAssistant => 'Defense Assistant',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
