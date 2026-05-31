<?php

namespace App\Enums;

enum NoteType: string
{
    case Manual = 'manual';
    case AiGenerated = 'ai_generated';
    case VoicePlaceholder = 'voice_placeholder';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
