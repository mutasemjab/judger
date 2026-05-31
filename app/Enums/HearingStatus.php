<?php

namespace App\Enums;

enum HearingStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
