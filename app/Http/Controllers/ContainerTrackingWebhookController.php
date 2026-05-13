<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SyncContainerMilestonesJob;
use App\Models\TripContainerTracking;
use App\Services\MarineTraffic\ContainerTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContainerTrackingWebhookController extends Controller
{
    /**
     * Kpler webhook IP whitelist
     * (network firewall should also enforce this)
     */
    private const ALLOWED_IPS = [
        '3.251.15.122',
        '52.215.44.244',
        '54.195.123.104',
    ];

    public function __construct(
        private readonly ContainerTrackingService $service
    )
    {
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            /**
             * Extra IP protection in production
             */
            if (
                app()->isProduction()
                && !in_array($request->ip(), self::ALLOWED_IPS, true)
            ) {
                Log::warning('Kpler webhook rejected: invalid IP', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Forbidden',
                ], 403);
            }

            $eventType = $request->input('data.attributes.eventType');

            Log::info('Kpler webhook received', [
                'event_type' => $eventType,
                'payload_id' => $request->input('data.id'),
            ]);

            match ($eventType) {
                'tracking_request_succeeded' =>
                $this->handleTrackingSucceeded($request),

                'tracking_request_failed' =>
                $this->handleTrackingFailed($request),

                'shipment_updated' =>
                $this->handleShipmentUpdated($request),

                default =>
                Log::info('Kpler webhook ignored unknown event', [
                    'event_type' => $eventType,
                ]),
            };

            return response()->json([
                'message' => 'ok',
            ]);
        } catch (\Throwable $e) {
            Log::error('Kpler webhook fatal error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /**
             * Return 200 if you do not want retries.
             * Return 500 if you want Kpler to retry.
             */
            return response()->json([
                'message' => 'error',
            ], 500);
        }
    }

    /**
     * EVENT: tracking_request_succeeded
     */
    private function handleTrackingSucceeded(Request $request): void
    {
        $trackingRequest = collect($this->included($request))
            ->firstWhere('type', 'tracking_request');

        if (!$trackingRequest) {
            Log::warning('tracking_request_succeeded missing tracking_request');
            return;
        }

        $trackingRequestId = $this->trackingRequestId($trackingRequest);

        if (!$trackingRequestId) {
            Log::warning('tracking_request_succeeded missing trackingRequestId');
            return;
        }

        $shipmentId =
            data_get(
                $trackingRequest,
                'relationships.shipment.data.shipmentId'
            )
            ?? data_get(
            $trackingRequest,
            'relationships.shipment.data.id'
        );

        $record = TripContainerTracking::where(
            'mt_tracking_request_id',
            $trackingRequestId
        )->first();

        if (!$record) {
            Log::warning('Unknown tracking request in success webhook', [
                'tracking_request_id' => $trackingRequestId,
            ]);
            return;
        }

        $record->update([
            'tracking_status' => 'active',
            'failed_reason' => null,
            'mt_shipment_id' => $shipmentId ?: $record->mt_shipment_id,
            'last_synced_at' => now(),
        ]);

        // Dispatch milestone sync now that shipment exists
        if ($record->mt_shipment_id) {
            SyncContainerMilestonesJob::dispatch($record->fresh());
        }

        Log::info('tracking_request_succeeded processed', [
            'trip_id' => $record->trip_id,
            'tracking_request_id' => $trackingRequestId,
            'shipment_id' => $record->mt_shipment_id,
        ]);
    }

    /**
     * Supports BOTH:
     *
     * "included": {...}
     * "included": [{...}]
     */
    private function included(Request $request): array
    {
        $included = $request->input('included', []);

        if (!is_array($included)) {
            return [];
        }

        /**
         * Single object payload
         */
        if (isset($included['type'])) {
            return [$included];
        }

        /**
         * Already array of objects
         */
        return $included;
    }

    private function trackingRequestId(array $trackingRequest): ?string
    {
        return $trackingRequest['trackingRequestId']
            ?? $trackingRequest['id']
            ?? null;
    }

    /**
     * EVENT: tracking_request_failed
     */
    private function handleTrackingFailed(Request $request): void
    {
        $trackingRequest = collect($this->included($request))
            ->firstWhere('type', 'tracking_request');

        if (!$trackingRequest) {
            Log::warning('tracking_request_failed missing tracking_request');
            return;
        }

        $trackingRequestId = $this->trackingRequestId($trackingRequest);

        if (!$trackingRequestId) {
            return;
        }

        $reason =
            data_get($trackingRequest, 'attributes.failed_reason')
            ?? data_get($trackingRequest, 'attributes.status')
            ?? 'Unknown';

        TripContainerTracking::where(
            'mt_tracking_request_id',
            $trackingRequestId
        )->update([
            'tracking_status' => 'failed',
            'failed_reason' => $reason,
            'last_synced_at' => now(),
        ]);

        Log::warning('tracking_request_failed processed', [
            'tracking_request_id' => $trackingRequestId,
            'reason' => $reason,
        ]);
    }

    /**
     * EVENT: shipment_updated
     */
    private function handleShipmentUpdated(Request $request): void
    {
        $shipment = collect($this->included($request))
            ->firstWhere('type', 'shipment');

        if (!$shipment) {
            // ... existing fallback unchanged
            return;
        }

        // ── NEW: resolve & pre-link mt_shipment_id before processing ──────────
        $shipmentId = $this->shipmentId($shipment);

        if ($shipmentId) {
            $record = TripContainerTracking::where('mt_shipment_id', $shipmentId)->first();

            // Not found by shipment ID — Kpler sent shipment_updated before tracking_request_succeeded.
            // Try linking via tracking request ID.
            if (!$record) {
                $trackingRequestId =
                    data_get($shipment, 'relationships.trackingRequest.data.trackingRequestId')
                    ?? data_get($shipment, 'relationships.trackingRequest.data.id');

                if ($trackingRequestId) {
                    $record = TripContainerTracking::where(
                        'mt_tracking_request_id', $trackingRequestId
                    )->first();

                    if ($record) {
                        // Stamp the shipment ID now so processWebhookShipment can find it
                        $record->update([
                            'mt_shipment_id' => $shipmentId,
                            'tracking_status' => 'active',
                        ]);

                        Log::info('shipment_updated: linked shipment via tracking_request_id (out-of-order)', [
                            'trip_id' => $record->trip_id,
                            'shipment_id' => $shipmentId,
                            'tracking_request_id' => $trackingRequestId,
                        ]);
                    }
                }
            }
        }


        $this->service->processWebhookShipment($shipment);

        if (!$shipmentId) return;

        $record = TripContainerTracking::where('mt_shipment_id', $shipmentId)->first();

        if (!$record) {
            Log::warning('shipment_updated unknown shipment', ['shipment_id' => $shipmentId]);
            return;
        }

        SyncContainerMilestonesJob::dispatch($record);

        Log::info('shipment_updated processed', [
            'trip_id' => $record->trip_id,
            'shipment_id' => $shipmentId,
        ]);
    }

    private function shipmentId(array $shipment): ?string
    {
        return $shipment['shipmentId']
            ?? $shipment['id']
            ?? null;
    }
}
