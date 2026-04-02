<?php

namespace App\Enums;

enum PortCategory: string
{
    case Port = 'port';
    case Icd = 'icd';
    case Cfs = 'cfs';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
