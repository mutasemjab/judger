<?php

namespace App\Enums;

enum MessageSourceType: string
{
    case CaseDocument = 'case_document';
    case KnowledgeBase = 'knowledge_base';
    case Mixed = 'mixed';
    case Web = 'web';
    case None = 'none';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
