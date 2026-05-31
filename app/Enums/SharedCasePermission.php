<?php

namespace App\Enums;

enum SharedCasePermission: string
{
    case View = 'view';
    case Comment = 'comment';
    case Edit = 'edit';
    case Admin = 'admin';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
