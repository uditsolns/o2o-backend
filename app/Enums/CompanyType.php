<?php

namespace App\Enums;

enum CompanyType: string
{
    case PvtLtd = 'pvt_ltd';
    case Llp = 'llp';
    case Proprietorship = 'proprietorship';
    case Partnership = 'partnership';
    case PublicLtd = 'public_ltd';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
