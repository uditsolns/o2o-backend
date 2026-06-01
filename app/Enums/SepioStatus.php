<?php

namespace App\Enums;

enum SepioStatus: string
{
    case Disabled = 'disabled';
    case Pending = 'pending';
    case Registered = 'registered';
    case DocsUploaded = 'docs_uploaded';
    case VerificationPending = 'verification_pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
