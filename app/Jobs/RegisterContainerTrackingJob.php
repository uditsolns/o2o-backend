<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\MarineTraffic\ContainerTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterContainerTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;

    public function __construct(private readonly Trip $trip)
    {
    }

    public function handle(ContainerTrackingService $service): void
    {
        $trip = $this->trip->fresh();

        if (empty($trip->container_number) || empty($trip->carrier_scac)) {
            Log::warning('RegisterContainerTrackingJob: missing container_number or carrier_scac', [
                'trip_id' => $trip->id,
            ]);
            return;
        }

        // Idempotency: if already active/pending, just sync milestones
        $existing = $trip->containerTracking;
        if ($existing && in_array($existing->tracking_status, ['active', 'pending'], true)) {
            Log::info('RegisterContainerTrackingJob: already registered, skipping re-registration', [
                'trip_id' => $trip->id,
                'tracking_status' => $existing->tracking_status,
            ]);
            if ($existing->isActive() && $existing->mt_shipment_id) {
                $service->syncMilestones($existing);
            }
            return;
        }

        $record = $service->registerTracking($trip);

        if ($record->isActive() && $record->mt_shipment_id) {
            $service->syncMilestones($record);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RegisterContainerTrackingJob failed permanently', [
            'trip_id' => $this->trip->id,
            'error' => $e->getMessage(),
        ]);
    }
}
