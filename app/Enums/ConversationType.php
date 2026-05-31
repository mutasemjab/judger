<?php

namespace App\Enums;

enum ConversationType: string
{
    case General = 'general';
    case Case = 'case';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
