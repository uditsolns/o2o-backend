<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Largest-Triangle-Three-Buckets downsampling for time series.
 *
 * Reduces a long sequence of (timestamp, lat, lng) points to a target count
 * while preserving the visual shape of the line on a map. Reference:
 * Sveinn Steinarsson, "Downsampling Time Series for Visual Representation"
 * (MSc thesis, University of Iceland, 2013).
 *
 * Usage:
 *   $sampler = new LttbDownsampler();
 *   $decimated = $sampler->downsample($points, 500);
 *
 * Input is an array of associative arrays with keys:
 *   - recorded_at: CarbonInterface|string|int (unix seconds)
 *   - lat: float
 *   - lng: float
 *
 * Output preserves the same shape, sorted by recorded_at ascending.
 */
class LttbDownsampler
{
    /**
     * Downsample a sequence of points to approximately $threshold points.
     *
     * @param  array<int, array{recorded_at: CarbonInterface|string|int, lat: float|int|null, lng: float|int|null}>  $points
     * @param  int  $threshold  Target output point count. If input length <= threshold, returns input as-is.
     * @return array<int, array{recorded_at: CarbonInterface, lat: float|null, lng: float|null}>
     */
    public function downsample(array $points, int $threshold): array
    {
        if ($threshold < 3) {
            // LTTB is undefined for < 3 points; just return what we have.
            return array_values(array_map([$this, 'normalize'], array_slice($points, 0, $threshold)));
        }

        if (count($points) <= $threshold) {
            return array_values(array_map([$this, 'normalize'], $points));
        }

        $normalized = array_map([$this, 'normalize'], $points);

        // Bucket size: the dataset (excluding first and last) split into threshold-2 buckets.
        $bucketSize = (count($normalized) - 2) / ($threshold - 2);

        $sampled = [];
        $sampled[] = $normalized[0]; // first point always kept

        $previousIndex = 0;
        $bucketStart = 1;

        for ($i = 0; $i < $threshold - 2; $i++) {
            // Compute the next bucket's average point (used as the "C" of the next triangle).
            $nextBucketStart = (int) floor(($i + 1) * $bucketSize) + 1;
            $nextBucketEnd = (int) floor(($i + 2) * $bucketSize) + 1;
            $nextBucketEnd = min($nextBucketEnd, count($normalized));

            $avgX = 0.0;
            $avgY = 0.0;
            $avgT = 0.0;
            $nextBucketLength = $nextBucketEnd - $nextBucketStart;

            for ($j = $nextBucketStart; $j < $nextBucketEnd; $j++) {
                $avgX += (float) $normalized[$j]['lng'];
                $avgY += (float) $normalized[$j]['lat'];
                $avgT += $this->timestampSeconds($normalized[$j]['recorded_at']);
            }

            if ($nextBucketLength > 0) {
                $avgX /= $nextBucketLength;
                $avgY /= $nextBucketLength;
                $avgT /= $nextBucketLength;
            } else {
                // Degenerate edge: re-use the previous selected point's time.
                $avgT = $this->timestampSeconds($normalized[$previousIndex]['recorded_at']);
            }

            // Within the current bucket, pick the point that forms the largest triangle
            // with the previously selected point and the next bucket's average.
            $previousX = (float) $normalized[$previousIndex]['lng'];
            $previousY = (float) $normalized[$previousIndex]['lat'];
            $previousT = $this->timestampSeconds($normalized[$previousIndex]['recorded_at']);

            $bucketEnd = (int) floor(($i + 1) * $bucketSize) + 1;
            $bucketEnd = min($bucketEnd, count($normalized) - 1);

            $maxArea = -1.0;
            $maxAreaIndex = $bucketStart;

            for ($j = $bucketStart; $j < $bucketEnd; $j++) {
                $area = $this->triangleArea(
                    $previousT, $previousX, $previousY,
                    $avgT,       $avgX,      $avgY,
                    $this->timestampSeconds($normalized[$j]['recorded_at']),
                    (float) $normalized[$j]['lng'],
                    (float) $normalized[$j]['lat']
                );

                if ($area > $maxArea) {
                    $maxArea = $area;
                    $maxAreaIndex = $j;
                }
            }

            $sampled[] = $normalized[$maxAreaIndex];
            $previousIndex = $maxAreaIndex;
            $bucketStart = (int) floor(($i + 1) * $bucketSize) + 1;
        }

        $sampled[] = $normalized[count($normalized) - 1]; // last point always kept

        return $sampled;
    }

    /**
     * Normalize a point: parse recorded_at to Carbon, cast lat/lng to float|null,
     * preserve source if present (used to identify line vs marker segments after LTTB).
     */
    private function normalize(array $point): array
    {
        $recordedAt = $point['recorded_at'] ?? null;
        if ($recordedAt instanceof CarbonInterface) {
            $ts = $recordedAt;
        } elseif (is_numeric($recordedAt)) {
            $ts = \Carbon\Carbon::createFromTimestamp((int) $recordedAt);
        } elseif (is_string($recordedAt) && $recordedAt !== '') {
            $ts = \Carbon\Carbon::parse($recordedAt);
        } else {
            $ts = \Carbon\Carbon::now();
        }

        $normalized = [
            'recorded_at' => $ts,
            'lat' => isset($point['lat']) && $point['lat'] !== null ? (float) $point['lat'] : null,
            'lng' => isset($point['lng']) && $point['lng'] !== null ? (float) $point['lng'] : null,
        ];

        // Preserve source if the input point has it (used by TripTrackingService
        // to group decimated points by tracking source after LTTB).
        if (isset($point['source'])) {
            $normalized['source'] = $point['source'];
        }

        return $normalized;
    }

    private function timestampSeconds(CarbonInterface $ts): float
    {
        return (float) $ts->getTimestamp() + ((float) $ts->micro / 1_000_000);
    }

    /**
     * Area of the triangle formed by three points (ax, ay), (bx, by), (cx, cy).
     * Note: we use (time, lng) for x and lat for y. The actual area is
     * meaningless in mixed units — we only use the *relative* size to pick
     * the most visually important point.
     */
    private function triangleArea(
        float $ax, float $ay,
        float $bx, float $by,
        float $cx, float $cy
    ): float {
        return abs(
            ($ax * ($by - $cy) + $bx * ($cy - $ay) + $cx * ($ay - $by)) / 2.0
        );
    }
}
