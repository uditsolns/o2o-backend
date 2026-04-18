<?php

namespace App\Enums;

enum TripSegmentTrackingSource: string
{
    case Gps = 'gps';
    case TclTracker = 'tcl_tracker';
    case ELock = 'e_lock';
    case DriverMobile = 'driver_mobile';
    case DriverSim = 'driver_sim';
    case FastTag = 'fast_tag';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
