<?php

namespace App\Enums;

enum LocationType: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Both = 'both';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
