<?php

namespace App\Enums;

enum KnowledgeDocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
