<?php

namespace App\Jobs;

use App\Models\TripContainerTracking;
use App\Services\MarineTraffic\ContainerTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Polls Kpler for all tracking requests that are still in `pending` status.
 * Kpler processes registration asynchronously — this closes the gap between
 * registration and the `tracking_request_succeeded` webhook arriving.
 *
 * Runs every 30 minutes (see routes/console.php).
 */
class CheckPendingTrackingRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(ContainerTrackingService $service): void
    {
        $pending = TripContainerTracking::where('tracking_status', 'pending')
            ->whereNotNull('mt_tracking_request_id')
            ->get();

        if ($pending->isEmpty()) return;

        Log::info('CheckPendingTrackingRequestsJob: checking', ['count' => $pending->count()]);

        foreach ($pending as $record) {
            try {
                $service->checkAndActivatePendingTracking($record);
            } catch (\Throwable $e) {
                Log::error('CheckPendingTrackingRequestsJob: check failed', [
                    'trip_id' => $record->trip_id,
                    'tracking_request_id' => $record->mt_tracking_request_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
