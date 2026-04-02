<?php

namespace App\Enums;

enum WalletCoastingType: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    
    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
