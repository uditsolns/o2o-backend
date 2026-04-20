<?php

namespace App\Http\Controllers;

use App\Jobs\SyncContainerMilestonesJob;
use App\Models\TripContainerTracking;
use App\Services\MarineTraffic\ContainerTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContainerTrackingWebhookController extends Controller
{
    // Kpler webhook IPs — whitelist enforced at network layer but double-checked here
    private const ALLOWED_IPS = ['3.251.15.122', '52.215.44.244', '54.195.123.104'];

    public function __construct(private readonly ContainerTrackingService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        // IP whitelist guard
        if (app()->isProduction() && !in_array($request->ip(), self::ALLOWED_IPS, true)) {
            Log::warning('ContainerTrackingWebhook: rejected IP', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $eventType = $request->input('data.attributes.eventType');

        Log::info('ContainerTrackingWebhook: received', ['event_type' => $eventType]);

        match ($eventType) {
            'shipment_updated' => $this->handleShipmentUpdated($request),
            'tracking_request_succeeded' => $this->handleTrackingSucceeded($request),
            'tracking_request_failed' => $this->handleTrackingFailed($request),
            default => Log::info('ContainerTrackingWebhook: unhandled event', ['type' => $eventType]),
        };

        return response()->json(['message' => 'ok']);
    }

    private function handleShipmentUpdated(Request $request): void
    {
        $shipment = collect($request->input('included', []))
            ->firstWhere('type', 'shipment');

        if (!$shipment) return;

        $this->service->processWebhookShipment($shipment);

        // Sync full milestones asynchronously
        $shipmentId = $shipment['shipmentId'] ?? null;
        if ($shipmentId) {
            $record = TripContainerTracking::where('mt_shipment_id', $shipmentId)->first();
            if ($record) {
                SyncContainerMilestonesJob::dispatch($record);
            }
        }
    }

    private function handleTrackingSucceeded(Request $request): void
    {
        $trackingRequest = collect($request->input('included', []))
            ->firstWhere('type', 'tracking_request');

        if (!$trackingRequest) return;

        $trackingRequestId = $trackingRequest['trackingRequestId'] ?? null;
        $shipmentId = $trackingRequest['relationships']['shipment']['data']['shipmentId'] ?? null;

        if (!$trackingRequestId) return;

        $record = TripContainerTracking::where('mt_tracking_request_id', $trackingRequestId)->first();

        if (!$record) return;

        $record->update([
            'tracking_status' => 'active',
            'mt_shipment_id' => $shipmentId ?? $record->mt_shipment_id,
        ]);

        // Now that we have a shipment ID, fetch full details
        if ($record->mt_shipment_id) {
            SyncContainerMilestonesJob::dispatch($record->fresh());
        }
    }

    private function handleTrackingFailed(Request $request): void
    {
        $trackingRequest = collect($request->input('included', []))
            ->firstWhere('type', 'tracking_request');

        $trackingRequestId = $trackingRequest['trackingRequestId'] ?? null;
        $reason = $trackingRequest['attributes']['failed_reason'] ?? 'Unknown';

        if (!$trackingRequestId) return;

        TripContainerTracking::where('mt_tracking_request_id', $trackingRequestId)
            ->update(['tracking_status' => 'failed', 'failed_reason' => $reason]);

        Log::warning('ContainerTrackingWebhook: tracking failed', [
            'tracking_request_id' => $trackingRequestId,
            'reason' => $reason,
        ]);
    }
}
