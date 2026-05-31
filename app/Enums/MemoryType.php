<?php

namespace App\Enums;

enum MemoryType: string
{
    case Fact = 'fact';
    case Party = 'party';
    case Date = 'date';
    case Deadline = 'deadline';
    case Claim = 'claim';
    case Defense = 'defense';
    case Evidence = 'evidence';
    case Risk = 'risk';
    case Task = 'task';
    case Strategy = 'strategy';
    case General = 'general';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
