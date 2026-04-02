<?php

namespace App\Enums;

enum TripDocType: string
{
    case EWayBill = 'e_way_bill';
    case EInvoice = 'e_invoice';
    case EPod = 'e_pod';
    case Supporting = 'supporting';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
