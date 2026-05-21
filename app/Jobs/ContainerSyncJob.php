<?php

namespace App\Jobs;

use App\Enums\TripStatus;
use App\Models\TripContainerTracking;
use App\Services\MarineTraffic\ContainerTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Nightly safety-net: re-fetches all active container shipments to compensate
 * for any missed webhooks during the day.
 */
class ContainerSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Kpler statuses indicating the sea journey is complete.
     * No point syncing these — the data won't change.
     */
    private const TERMINAL_KPLER_STATUSES = [
        'left_the_port_of_discharge',
        'completed',
    ];

    public function handle(ContainerTrackingService $service): void
    {
        $records = TripContainerTracking::with('trip')
            ->where('tracking_status', 'active')
            ->whereNotNull('mt_shipment_id')
            // Skip if Kpler already considers the journey done
            ->whereNotIn('transportation_status', self::TERMINAL_KPLER_STATUSES)
            ->whereHas('trip', fn($q) => $q->whereNotIn('status', [
                TripStatus::Completed->value,
                TripStatus::Delivered->value,
            ]))
            ->get();

        if ($records->isEmpty()) return;

        Log::info('ContainerSyncJob: syncing active trackings', ['count' => $records->count()]);

        foreach ($records as $record) {
            try {
                $service->refreshShipment($record);
                $service->syncMilestones($record->fresh());
            } catch (\Throwable $e) {
                Log::error('ContainerSyncJob: sync failed', [
                    'trip_id' => $record->trip_id,
                    'shipment_id' => $record->mt_shipment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('ContainerSyncJob: completed');
    }
}
