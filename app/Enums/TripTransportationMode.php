<?php

namespace App\Enums;

enum TripTransportationMode: string
{
    case Road = 'road';
    case Sea = 'sea';
    case Multimodal = 'multimodal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
