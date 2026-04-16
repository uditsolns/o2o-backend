<?php

namespace App\Services;

use App\Enums\SealStatus;
use App\Enums\SealOrderStatus;
use App\Models\Seal;
use App\Models\SealOrder;
use App\Models\SealStatusLog;
use App\Services\Sepio\SepioSealService;
use Illuminate\Support\Facades\DB;

readonly class SealService
{
    public function __construct(private SepioSealService $sepioSealService)
    {
    }

    /**
     * Bulk-ingest seal numbers from Sepio after an order is dispatched.
     * Called by webhook or manual admin trigger.
     */
    public function ingestFromOrder(SealOrder $order, array $sealNumbers, string $dispatchedAt): void
    {
        abort_if(
            count($sealNumbers) !== $order->quantity,
            422,
            'Seal count (' . count($sealNumbers) . ') does not match order quantity (' . $order->quantity . ').'
        );

        DB::transaction(function () use ($order, $sealNumbers, $dispatchedAt) {
            $now = now();

            $records = array_map(fn(string $number) => [
                'customer_id' => $order->customer_id,
                'seal_order_id' => $order->id,
                'trip_id' => null,
                'seal_number' => $number,
                'status' => SealStatus::InInventory->value,
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

            $order->update([
                'status' => SealOrderStatus::Completed,
                'seals_dispatched_at' => $dispatchedAt,
                'seals_delivered_at' => $now,
            ]);
        });
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
        $status = $sepioPayload['status'];           // valid|tampered|broken|unknown
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

            // TODO: move trip to at_port from in_transit, when seal is scanned at origin port

            // Escalate our internal status on tamper
            if ($status === 'tampered' && $seal->status !== SealStatus::Tampered) {
                $updates['status'] = SealStatus::Tampered;
            }

            $seal->update($updates);
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
