<?php

namespace App\Enums;

enum SealOrderStatus: string
{
    case IlPending = 'il_pending';
    case IlApproved = 'il_approved';
    case IlRejected = 'il_rejected';
    case IlParked = 'il_parked';
    case MfgPending = 'mfg_pending';
    case InProgress = 'in_progress';
    case OrderPlaced = 'order_placed';
    case InTransit = 'in_transit';
    case MfgCompleted = 'mfg_completed';
    case Completed = 'completed';
    case MfgRejected = 'mfg_rejected';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
