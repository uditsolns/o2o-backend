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

    public static function required(): array
    {
        return array_map(
            fn(self $type) => $type->value,
            array_filter(
                self::cases(),
                fn(self $type) => $type->isRequired()
            )
        );
    }

    public function isRequired(): bool
    {
        return in_array($this, [
            self::GstCrt,
            self::PanCard,
            self::IecCert,
            self::CertificateOfRegistration,
            self::SelfStuffingCert,
        ], true);
    }
}
