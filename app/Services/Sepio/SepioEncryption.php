<?php

namespace App\Services\Sepio;

class SepioEncryption
{
    private string $key;
    private string $iv;

    public function __construct()
    {
        $this->key = config('sepio.encrypt_key');
        $this->iv = config('sepio.encrypt_iv');
    }

    /**
     * AES-256-CTR, NoPadding, output as hex string.
     * Matches CryptoJS reference implementation exactly.
     */
    public function encrypt(string $plainText): string
    {
        $encrypted = openssl_encrypt(
            $plainText,
            'AES-256-CTR',
            $this->key,
            OPENSSL_RAW_DATA | OPENSSL_NO_PADDING,
            $this->iv
        );

        return bin2hex($encrypted);
    }
}
