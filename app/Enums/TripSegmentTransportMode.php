<?php

namespace App\Enums;

enum TripSegmentTransportMode: string
{
    case Road = 'road';
    case Sea = 'sea';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
