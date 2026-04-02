<?php

namespace App\Enums;

enum SealStatus: string
{
    case InInventory = 'in_inventory';
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Used = 'used';
    case Tampered = 'tampered';
    case Lost = 'lost';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
