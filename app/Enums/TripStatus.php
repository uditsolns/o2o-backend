<?php

namespace App\Enums;

enum TripStatus: string
{
    case Draft = 'draft';
    case InTransit = 'in_transit';
    case AtPort = 'at_port';
    case OnVessel = 'on_vessel';
    case VesselArrived = 'vessel_arrived';
    case Delivered = 'delivered';
    case Completed = 'completed';

    /** Returns the valid next statuses from this one. */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::InTransit],
            self::InTransit => [self::AtPort],
            self::AtPort => [self::OnVessel],
            self::OnVessel => [self::VesselArrived],
            self::VesselArrived => [self::Delivered],
            self::Delivered => [self::Completed],
            self::Completed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
