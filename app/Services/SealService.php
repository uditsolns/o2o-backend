<?php

namespace App\Services;

use App\Enums\SealOrderStatus;
use App\Enums\SealStatus;
use App\Enums\SepioSealStatus;
use App\Enums\TripStatus;
use App\Models\Seal;
use App\Models\SealOrder;
use App\Models\SealStatusLog;
use App\Models\TripEvent;
use App\Services\Sepio\SepioSealService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class SealService
{
    public function __construct(private SepioSealService $sepioSealService)
    {
    }

    /**
     * Bulk-ingest seal numbers from an order.
     *
     * When $complete = true  (manual IL trigger via SealController): seals are inserted
     *                         and the order is immediately marked Completed.
     *
     * When $complete = false (automatic Sepio allocation poll): seals are inserted but the
     *                         order status is left unchanged — the order will be marked
     *                         Completed only once Sepio also marks it done (MfgCompleted).
     *                         Until then the seals exist in our system but are "not activated"
     *                         on Sepio's side and will fail the availability check.
     */
    public function ingestFromOrder(SealOrder $order, array $sealNumbers, string $dispatchedAt, bool $complete = true): void
    {
        abort_if(
            count($sealNumbers) !== $order->quantity,
            422,
            'Seal count (' . count($sealNumbers) . ') does not match order quantity (' . $order->quantity . ').'
        );

        DB::transaction(function () use ($order, $sealNumbers, $dispatchedAt, $complete) {
            $now = now();

            $records = array_map(fn(string $number) => [
                'customer_id' => $order->customer_id,
                'seal_order_id' => $order->id,
                'trip_id' => null,
                'seal_number' => $number,
                'status' => $complete
                    ? SealStatus::InInventory->value
                    : SealStatus::Inactive->value,   // blocked until order completes
                'sepio_status' => 'unknown',
                'last_scan_at' => null,
                'delivered_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $sealNumbers);

            // Insert in chunks to avoid packet size limits
            foreach (array_chunk($records, 500) as $chunk) {
                Seal::insert($chunk);
            }

            if ($complete) {
                $order->update([
                    'status' => SealOrderStatus::Completed,
                    'seals_delivered_at' => $now,
                    // Only set dispatched_at as fallback if not already stamped by the status sync job
                    ...(!$order->seals_dispatched_at ? ['seals_dispatched_at' => $dispatchedAt] : []),
                ]);
            } else {
                // Stamp dispatched_at if not already set — don't touch status
                if (!$order->seals_dispatched_at) {
                    $order->update(['seals_dispatched_at' => $dispatchedAt]);
                }
            }
        });
    }

    /**
     * Advance a MfgCompleted order to Completed once its seals are fully ingested.
     * Idempotent — safe to call from both the allocation poll and status sync jobs.
     *
     * The two jobs converge here:
     *  - SepioSealAllocationPollJob calls this after ingesting seals
     *    (in case the order was already MfgCompleted before allocation arrived)
     *  - SepioOrderStatusSyncJob calls this when Sepio reports "completed"
     *    (in case seals were already ingested before Sepio finished)
     */
    public function completeOrderIfSealsReady(SealOrder $order): void
    {
        if ($order->status === SealOrderStatus::Completed) {
            return;
        }

        if ($order->status !== SealOrderStatus::MfgCompleted) {
            return; // Sepio hasn't finished their side yet — wait for status sync job
        }

        $ingested = $order->seals()->count();

        if ($ingested < $order->quantity) {
            Log::info('SealService: seals not fully ingested yet, waiting for allocation poll', [
                'order_id' => $order->id,
                'ingested' => $ingested,
                'expected' => $order->quantity,
            ]);
            return;
        }

        DB::transaction(function () use ($order) {
            // Activate all seals for this order — they are now usable
            $order->seals()
                ->where('status', SealStatus::Inactive)
                ->update(['status' => SealStatus::InInventory->value]);

            $order->update([
                'status' => SealOrderStatus::Completed,
                'seals_delivered_at' => $order->seals_delivered_at ?? now(),
            ]);
        });

        Log::info('SealService: order completed — seals activated', [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Assign a seal to a trip. Called from TripService when trip is started.
     */
    public function assignToTrip(Seal $seal, int $tripId): Seal
    {
        abort_if(
            !$seal->isAvailable(),
            422,
            "Seal {$seal->seal_number} is not available (current status: {$seal->status->value})."
        );

        $customer = $seal->customer;

        // Check availability with Sepio before assigning
        if ($customer->sepio_company_id) {
            $check = $this->sepioSealService->checkSealAvailability($customer, $seal);

            if (!$check['available']) {
                abort(422, "Seal {$seal->seal_number} is not available on seal provider: {$check['message']}");
            }
        }

        $seal->update([
            'trip_id' => $tripId,
            'status' => SealStatus::Assigned,
        ]);

        return $seal->fresh();
    }

    /**
     * Release a seal back to inventory (e.g. trip cancelled before dispatch).
     */
    public function releaseFromTrip(Seal $seal): Seal
    {
        $seal->update([
            'trip_id' => null,
            'status' => SealStatus::InInventory,
        ]);

        return $seal->fresh();
    }

    /**
     * Sync seal status from Sepio poll response.
     * Appends to seal_status_logs; updates seals.sepio_status + last_scan_at.
     */
    public function syncStatus(Seal $seal, array $sepioPayload): Seal
    {
        $status = $sepioPayload['status'];
        $scanLocation = $sepioPayload['location'] ?? null;
        $scannedLat = $sepioPayload['lat'] ?? null;
        $scannedLng = $sepioPayload['lng'] ?? null;
        $scannedBy = $sepioPayload['scanned_by'] ?? null;
        $checkedAt = $sepioPayload['scanned_at'] ?? now();

        DB::transaction(function () use ($seal, $status, $scanLocation, $scannedLat, $scannedLng, $scannedBy, $checkedAt, $sepioPayload) {
            SealStatusLog::create([
                'customer_id' => $seal->customer_id,
                'seal_id' => $seal->id,
                'trip_id' => $seal->trip_id,
                'status' => $status,
                'scan_location' => $scanLocation,
                'scanned_lat' => $scannedLat,
                'scanned_lng' => $scannedLng,
                'scanned_by' => $scannedBy,
                'raw_response' => $sepioPayload,
                'checked_at' => $checkedAt,
            ]);

            $updates = [
                'sepio_status' => $status,
                'last_scan_at' => $checkedAt,
            ];

            // Escalate our internal status on tamper
            if ($status === 'tampered' && $seal->status !== SealStatus::Tampered) {
                $updates['status'] = SealStatus::Tampered;
            }

            $seal->update($updates);

            // Auto-advance trip from in_transit → at_port when seal scanned at origin port
            if ($seal->trip_id && in_array($status, [SepioSealStatus::Valid, SepioSealStatus::Unknown])) {
                $trip = $seal->trip;
                if (
                    $trip &&
                    $trip->status === TripStatus::InTransit &&
                    $trip->origin_port_code &&
                    $scanLocation &&
                    str_contains(strtoupper($scanLocation), '(' . strtoupper($trip->origin_port_code) . ')')
                ) {
                    $trip->update(['status' => TripStatus::AtPort]);

                    TripEvent::create([
                        'customer_id' => $trip->customer_id,
                        'trip_id' => $trip->id,
                        'event_type' => 'status_changed',
                        'previous_status' => TripStatus::InTransit,
                        'new_status' => TripStatus::AtPort,
                        'event_data' => ['scan_location' => $scanLocation, 'triggered_by' => 'seal_scan'],
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return $seal->fresh();
    }

    /**
     * Mark a seal as lost.
     */
    public function markLost(Seal $seal): Seal
    {
        $seal->update(['status' => SealStatus::Lost]);
        return $seal->fresh();
    }
}
