<?php

namespace App\Enums;

enum CustomerDocType: string
{
    case GstCrt = 'gst_cert';
    case PanCard = 'pan_card';
    case IecCert = 'iec_cert';
    case CertificateOfRegistration = 'certificate_of_registration';
    case SelfStuffingCert = 'self_stuffing_cert';
    case ChaAuthLetter = 'cha_auth_letter';
    case Tin = 'tin';
    case Supporting = 'supporting';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Required for ALL customers regardless of Sepio status.
     */
    public static function requiredBasic(): array
    {
        return [self::GstCrt->value];
    }

    /**
     * Required additionally when customer has Sepio integration enabled.
     */
    public static function requiredForSepio(): array
    {
        return [
            self::IecCert->value,
            self::PanCard->value,
            self::CertificateOfRegistration->value,
            self::SelfStuffingCert->value,
        ];
    }

    /**
     * Full required list for a given context.
     * Keep the old required() as a convenience alias that returns all Sepio docs
     * (used internally by Sepio onboarding service).
     */
    public static function required(bool $sepioEnabled = false): array
    {
        return $sepioEnabled
            ? array_merge(self::requiredBasic(), self::requiredForSepio())
            : self::requiredBasic();
    }
}
