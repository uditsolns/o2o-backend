<?php

namespace App\Exceptions;

use RuntimeException;

class SepioException extends RuntimeException
{
    public function __construct(
        string                  $message,
        private readonly ?array $sepioPayload = null,
        int                     $httpStatus = 422
    )
    {
        parent::__construct($message, $httpStatus);
    }

    public function getSepioPayload(): ?array
    {
        return $this->sepioPayload;
    }

    public function getHttpStatus(): int
    {
        return $this->getCode() ?: 422;
    }

    public static function fromResponse(array $json, string $fallback = 'Sepio request failed.'): self
    {
        return new self(static::extractMessage($json) ?: $fallback, $json);
    }

    public static function extractMessage(array $json): string
    {
        if (!empty($json['error']['errLog'])) {
            return implode('; ', (array)$json['error']['errLog']);
        }
        if (!empty($json['error']['message1'])) {
            return trim($json['error']['message1'] . ' ' . ($json['error']['message2'] ?? ''));
        }
        if (!empty($json['error']['message'])) {
            return $json['error']['message'];
        }
        if (!empty($json['responseMessage']) && ($json['statusCode'] ?? 200) !== 200) {
            return $json['responseMessage'];
        }
        if (!empty($json['message'])) {
            return $json['message'];
        }
        return '';
    }
}
