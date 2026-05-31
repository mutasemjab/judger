<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Closed = 'closed';
    case Archived = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
