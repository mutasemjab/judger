<?php

namespace App\Enums;

enum PlanName: string
{
    case Free = 'free';
    case Premium = 'premium';
    case Enterprise = 'enterprise';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
