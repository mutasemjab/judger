<?php

namespace App\Enums;

enum UserType: string
{
    case Lawyer = 'lawyer';
    case Individual = 'individual';
    case LawFirm = 'law_firm';
    case LawStudent = 'law_student';

    public function label(): string
    {
        return match($this) {
            self::Lawyer => 'Lawyer',
            self::Individual => 'Individual',
            self::LawFirm => 'Law Firm',
            self::LawStudent => 'Law Student',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
