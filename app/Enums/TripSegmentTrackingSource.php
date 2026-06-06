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
    case VesselAis = 'vessel_ais';

    /** Sources that are checkpoint events — rendered as markers, not a connected line. */
    public static function markerSources(): array
    {
        return [self::FastTag->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
