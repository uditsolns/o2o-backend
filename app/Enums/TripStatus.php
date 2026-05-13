<?php

namespace App\Enums;

enum TripStatus: string
{
    case Draft = 'draft';
    case InTransit = 'in_transit';
    case AtPort = 'at_port';
    case OnVessel = 'on_vessel';
    case InTransshipment = 'in_transshipment';
    case VesselArrived = 'vessel_arrived';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Completed = 'completed';

    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::InTransit],
            self::InTransit => [self::AtPort],
            self::AtPort => [self::OnVessel],
            self::OnVessel => [self::InTransshipment, self::VesselArrived],
            self::InTransshipment => [self::OnVessel, self::VesselArrived],
            self::VesselArrived => [self::OutForDelivery, self::Delivered],
            self::OutForDelivery => [self::Delivered],
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
        return array_column(self::cases(), 'value');
    }
}
