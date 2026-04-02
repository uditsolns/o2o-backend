<?php

namespace App\Enums;

enum SepioSealStatus: string
{
    case Valid = 'valid';
    case Tampered = 'tampered';
    case Broken = 'broken';
    case Unknown = 'unknown';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
