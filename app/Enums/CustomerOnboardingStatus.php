<?php

namespace App\Enums;

enum CustomerOnboardingStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case IlParked = 'il_parked';
    case IlApproved = 'il_approved';
    case IlRejected = 'il_rejected';
    case MfgRejected = 'mfg_rejected';
    case Completed = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
