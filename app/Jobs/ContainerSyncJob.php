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
 * Safety-net job that re-fetches shipment snapshots and milestone timelines
 * for all active container trackings. Compensates for any webhook delivery
 * failures or network outages that may have caused events to be missed.
 *
 * Runs daily at midnight (see routes/console.php).
 */
class ContainerSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(ContainerTrackingService $service): void
    {
        $records = TripContainerTracking::with('trip')
            ->where('tracking_status', 'active')
            ->whereNotNull('mt_shipment_id')
            ->whereHas('trip', fn($q) => $q->whereNotIn('status', [
                TripStatus::Completed->value,
                TripStatus::Delivered->value,
            ]))
            ->get();

        if ($records->isEmpty()) return;

        Log::info('NightlyContainerSyncJob: syncing active trackings', ['count' => $records->count()]);

        foreach ($records as $record) {
            try {
                // 1. Re-fetch shipment summary → updates snapshot + auto-advances status
                $service->refreshShipment($record);

                // 2. Re-fetch full milestone timeline → fine-grained status advancement
                $service->syncMilestones($record->fresh());

            } catch (\Throwable $e) {
                Log::error('NightlyContainerSyncJob: sync failed', [
                    'trip_id' => $record->trip_id,
                    'shipment_id' => $record->mt_shipment_id,
                    'error' => $e->getMessage(),
                ]);
                // Continue to next record — don't let one failure stop the batch
            }
        }

        Log::info('NightlyContainerSyncJob: completed');
    }
}
