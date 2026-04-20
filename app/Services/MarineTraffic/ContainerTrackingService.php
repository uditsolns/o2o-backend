<?php

namespace App\Services\MarineTraffic;

use App\Models\Trip;
use App\Models\TripContainerTracking;
use App\Models\TripShipmentMilestone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContainerTrackingService
{
    private function http()
    {
        return Http::baseUrl(config('marinetraffic.container_base_url'))
            ->withHeader('X-Container-API-Key', config('marinetraffic.container_api_key'))
            ->timeout(20)
            ->retry(2, 1000);
    }

    /**
     * Register a container with Kpler for tracking.
     * Called once when trip transitions to OnVessel (container loaded).
     */
    public function registerTracking(Trip $trip): TripContainerTracking
    {
        $record = TripContainerTracking::firstOrCreate(
            ['trip_id' => $trip->id],
            [
                'customer_id' => $trip->customer_id,
                'container_number' => $trip->container_number,
                'carrier_scac' => $trip->carrier_scac,
                'tracking_status' => 'not_registered',
            ]
        );

        $response = $this->http()->post('/tracking-requests', [
            'data' => [[
                'type' => 'tracking_request',
                'attributes' => [
                    'referenceNumberType' => 'container',
                    'referenceNumber' => $trip->container_number,
                    'scac' => $trip->carrier_scac,
                ],
            ]],
        ]);

        if ($response->failed()) {
            $record->update([
                'tracking_status' => 'failed',
                'failed_reason' => $response->json('errors.0.description') ?? 'Registration failed',
            ]);
            Log::error('ContainerTrackingService: registration failed', [
                'trip_id' => $trip->id,
                'response' => $response->json(),
            ]);
            return $record;
        }

        $item = $response->json('data.0') ?? $response->json('data') ?? null;

        $trackingRequestId = $item['trackingRequestId'] ?? null;
        $shipmentId = $item['relationships']['shipment']['data']['shipmentId'] ?? null;
        $status = $item['attributes']['status'] ?? 'pending';

        $record->update([
            'mt_tracking_request_id' => $trackingRequestId,
            'mt_shipment_id' => $shipmentId,
            'tracking_status' => $status === 'success' ? 'active' : 'pending',
        ]);

        Log::info('ContainerTrackingService: registered', [
            'trip_id' => $trip->id,
            'tracking_request_id' => $trackingRequestId,
            'shipment_id' => $shipmentId,
        ]);

        return $record->fresh();
    }

    /**
     * Process a shipment_updated webhook payload — update snapshot + milestones.
     */
    public function processWebhookShipment(array $shipment): void
    {
        $shipmentId = $shipment['shipmentId'] ?? null;
        if (!$shipmentId) return;

        $record = TripContainerTracking::where('mt_shipment_id', $shipmentId)->first();
        if (!$record) {
            Log::warning('ContainerTrackingService: unknown shipmentId in webhook', ['id' => $shipmentId]);
            return;
        }

        $attrs = $shipment['attributes'] ?? [];
        $currentVessel = $attrs['currentVessel'] ?? null;
        $pos = $currentVessel['latestPosition'] ?? null;
        $insights = $attrs['insights'] ?? [];
        $pol = $attrs['portOfLoading'] ?? null;
        $pod = $attrs['portOfDischarge'] ?? null;

        $record->update([
            'tracking_status' => 'active',
            'transportation_status' => $attrs['transportationStatus'] ?? null,
            'arrival_delay_days' => $insights['arrivalDelayDays'] ?? null,
            'initial_carrier_eta' => $insights['initialCarrierEta'] ?? null,
            'has_rollover' => !empty($insights['rollover']),
            'pol_name' => $pol['port']['name'] ?? null,
            'pol_unlocode' => $pol['port']['unlocode'] ?? null,
            'pod_name' => $pod['port']['name'] ?? null,
            'pod_unlocode' => $pod['port']['unlocode'] ?? null,
            'current_vessel_name' => $currentVessel['name'] ?? null,
            'current_vessel_imo' => $currentVessel['imo'] ?? null,
            'current_vessel_lat' => $pos['lat'] ?? null,
            'current_vessel_lng' => $pos['lon'] ?? null,
            'current_vessel_speed' => $pos['speed'] ?? null,
            'current_vessel_heading' => $pos['heading'] ?? null,
            'current_vessel_geo_area' => $pos['geographicalArea'] ?? null,
            'current_vessel_position_at' => now(),
            'last_synced_at' => now(),
            'raw_shipment_snapshot' => $shipment,
        ]);

        // Sync vessel IMO + SHIP_ID onto trip for AIS polling
        if (!empty($currentVessel['imo'])) {
            $record->trip->updateQuietly([
                'vessel_imo_number' => $currentVessel['imo'],
                'vessel_name' => $currentVessel['name'] ?? $record->trip->vessel_name,
            ]);
        }

        Log::info('ContainerTrackingService: shipment snapshot updated', [
            'trip_id' => $record->trip_id,
            'shipment_id' => $shipmentId,
        ]);
    }

    /**
     * Fetch full transportation timeline from Kpler and sync milestones.
     */
    public function syncMilestones(TripContainerTracking $record): void
    {
        if (!$record->mt_shipment_id) return;

        $response = $this->http()
            ->get("/shipments/{$record->mt_shipment_id}/transportation-timeline");

        if ($response->failed()) {
            Log::warning('ContainerTrackingService: milestone fetch failed', [
                'shipment_id' => $record->mt_shipment_id,
            ]);
            return;
        }

        $attrs = $response->json('data.attributes') ?? [];
        $locations = collect($attrs['locations'] ?? [])->keyBy('id');
        $vessels = collect($attrs['vessels'] ?? [])->keyBy('id');

        // Merge equipment + transport events into one ordered set
        $allEvents = array_merge(
            $attrs['equipmentEvents'] ?? [],
            $attrs['transportEvents'] ?? []
        );

        usort($allEvents, fn($a, $b) => ($a['eventOrder'] ?? 0) <=> ($b['eventOrder'] ?? 0));

        foreach ($allEvents as $event) {
            $eventId = $event['id'] ?? null;
            if (!$eventId) continue;

            try {
                $loc = $locations[$event['locationId'] ?? ''] ?? null;
                $vessel = $vessels[$event['vesselId'] ?? ''] ?? null;

                TripShipmentMilestone::updateOrCreate(
                    ['trip_id' => $record->trip_id, 'mt_event_id' => $eventId],
                    [
                        'customer_id' => $record->customer_id,
                        'event_type' => $event['equipmentEventTypeName'] ?? $event['transportEventTypeName'] ?? 'unknown',
                        'event_classifier' => $event['eventClassifierCode'] ?? 'planned',
                        'location_name' => $loc['name'] ?? null,
                        'location_unlocode' => $loc['unlocode'] ?? null,
                        'location_country' => $loc['country'] ?? null,
                        'location_lat' => $loc['lat'] ?? null,
                        'location_lng' => $loc['lon'] ?? null,
                        'terminal_name' => $loc['terminal']['name'] ?? null,
                        'location_type' => $loc['type'] ?? null,
                        'vessel_name' => $vessel['name'] ?? null,
                        'vessel_imo' => $vessel['imo'] ?? null,
                        'voyage_number' => $vessel['voyageNumber'] ?? null,
                        'sequence_order' => $event['eventOrder'] ?? 0,
                        'occurred_at' => $event['eventDateTime'] ?? null,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('ContainerTrackingService: failed to upsert milestone', [
                    'trip_id' => $record->trip_id,
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);
                // continue — don't let one bad event kill the full sync
            }
        }

        Log::info('ContainerTrackingService: milestones synced', [
            'trip_id' => $record->trip_id,
            'event_count' => count($allEvents),
        ]);
    }
}
