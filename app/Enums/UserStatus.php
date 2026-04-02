<?php

namespace App\Enums;

enum UserStatus: string {
    case Invited   = 'invited';
    case Active    = 'active';
    case Inactive  = 'inactive';
    case Suspended = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), "value");
    }
}
