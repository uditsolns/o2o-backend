<?php

namespace App\Enums;

enum TripType: string
{
    case Import = 'import';
    case Export = 'export';
    case Domestic = 'domestic';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
