<?php

namespace App\Support;

/**
 * Google Encoded Polyline encoder.
 *
 * Reference format: https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 *
 * Each lat/lng is encoded as a signed 5-bit varint (the delta from the
 * previous point), inverted for negatives, with bit 0x20 set for non-zero
 * chunks, and ASCII-shifted by 63.
 *
 * Typical payload for 500 decimated GPS points: ~3 KB.
 */
class GooglePolyline
{
    private const PRECISION = 1e5; // 5 decimal places

    /**
     * Encode a list of [lat, lng] points as a Google polyline string.
     *
     * @param  array<int, array{0: float|int, 1: float|int}|array{lat: float|int, lng: float|int}>  $points
     */
    public function encode(array $points): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $point) {
            [$lat, $lng] = $this->extractPair($point);

            // Skip nulls — emit a tiny segment break by emitting the previous
            // value so the decoder doesn't pull wildly. Pure-null points collapse
            // to a no-op (no chars emitted), which is the desired behavior.
            if ($lat === null || $lng === null) {
                continue;
            }

            $lat = (int) round($lat * self::PRECISION);
            $lng = (int) round($lng * self::PRECISION);

            $encoded .= $this->encodeSigned($lat - $prevLat);
            $encoded .= $this->encodeSigned($lng - $prevLng);

            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $encoded;
    }

    /**
     * Accept either [lat, lng] tuples or ['lat' => ..., 'lng' => ...] assoc.
     */
    private function extractPair(array $point): array
    {
        if (array_key_exists(0, $point) || array_key_exists(1, $point)) {
            return [$point[0] ?? null, $point[1] ?? null];
        }
        return [$point['lat'] ?? null, $point['lng'] ?? null];
    }

    private function encodeSigned(int $value): string
    {
        // Left-bit flip: positives become negative, negatives become positive
        // — this makes the varint unsigned-friendly (smaller magnitudes sort
        // lexically).
        $value = $value < 0 ? ~($value << 1) : ($value << 1);

        $encoded = '';
        while ($value >= 0x20) {
            $encoded .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }
        $encoded .= chr($value + 63);
        return $encoded;
    }
}
