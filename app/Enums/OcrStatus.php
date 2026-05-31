<?php

namespace App\Enums;

enum OcrStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case NotRequired = 'not_required';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
