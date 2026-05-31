<?php

namespace App\Enums;

enum UsagePeriod: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
