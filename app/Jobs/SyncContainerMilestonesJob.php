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

class SyncContainerMilestonesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly TripContainerTracking $record)
    {
    }

    public function handle(ContainerTrackingService $service): void
    {
        $service->syncMilestones($this->record->fresh());
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncContainerMilestonesJob failed', [
            'trip_id' => $this->record->trip_id,
            'shipment_id' => $this->record->mt_shipment_id,
            'error' => $e->getMessage(),
        ]);
    }
}
