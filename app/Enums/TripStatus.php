<?php

namespace App\Enums;

enum TripStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Completed = 'completed';

    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active],
            self::Active => [self::OutForDelivery, self::Delivered],
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

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
        };
    }
}
