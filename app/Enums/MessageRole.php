<?php

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
