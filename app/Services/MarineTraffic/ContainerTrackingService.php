<?php

namespace App\Services\MarineTraffic;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripContainerTracking;
use App\Models\TripEvent;
use App\Models\TripShipmentMilestone;
use App\Jobs\SyncContainerMilestonesJob;
use Carbon\Carbon;
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

    // Registration

    /**
     * Register a container with Kpler for tracking.
     * Handles the `resource_already_exists` error gracefully — recovers the
     * existing tracking request ID instead of treating it as a hard failure.
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

        if ($record->tracking_status === 'active') {
            return $record;
        }

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

        // Check body-level errors FIRST (Kpler returns HTTP 200 even for errors)
        $errors = $response->json('errors', []);

        $alreadyExists = collect($errors)->first(
            fn($e) => ($e['code'] ?? '') === 'resource_already_exists'
        );

        if ($alreadyExists) {
            $aboutUrl = $alreadyExists['links']['about'] ?? null;
            $existingRequestId = $aboutUrl
                ? basename(parse_url($aboutUrl, PHP_URL_PATH))
                : null;

            $record->update([
                'mt_tracking_request_id' => $existingRequestId,
                'tracking_status' => 'pending',
                'failed_reason' => null,
            ]);

            Log::info('ContainerTrackingService: container already registered — reusing', [
                'trip_id' => $trip->id,
                'tracking_request_id' => $existingRequestId,
            ]);

            return $record->fresh();
        }

        // Now check for genuine HTTP failures
        if ($response->failed()) {
            $failedReason = $response->json('errors.0.description')
                ?? $response->json('message')
                ?? "HTTP {$response->status()}";

            $record->update([
                'tracking_status' => 'failed',
                'failed_reason' => $failedReason,
            ]);

            Log::error('ContainerTrackingService: registration failed', [
                'trip_id' => $trip->id,
                'status' => $response->status(),
                'errors' => $errors,
                'failed_reason' => $failedReason,
            ]);

            return $record;
        }

        // Success path
        $item = $response->json('data.0') ?? $response->json('data') ?? null;

        $trackingRequestId =
            $item['trackingRequestId']
            ?? $item['id']
            ?? null;

        $shipmentId =
            data_get($item, 'relationships.shipment.data.shipmentId')
            ?? data_get($item, 'relationships.shipment.data.id');

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
            'status' => $status,
        ]);

        return $record->fresh();
    }

    // Pending status check (called by CheckPendingTrackingRequestsJob)

    /**
     * Poll Kpler for a pending tracking request to see if it has been activated
     * and been assigned a shipment ID.
     */
    public function checkAndActivatePendingTracking(TripContainerTracking $record): void
    {
        if (!$record->mt_tracking_request_id) return;

        $response = $this->http()
            ->get("/tracking-requests/{$record->mt_tracking_request_id}");

        if ($response->failed()) {
            Log::warning('ContainerTrackingService: pending check failed', [
                'trip_id' => $record->trip_id,
                'tracking_request_id' => $record->mt_tracking_request_id,
                'status' => $response->status(),
            ]);
            return;
        }

        $data = $response->json('data') ?? [];
        $status = $data['attributes']['status'] ?? null;

        if ($status === 'success') {
            $shipmentId =
                data_get($data, 'relationships.shipment.data.shipmentId')
                ?? data_get($data, 'relationships.shipment.data.id');

            $record->update([
                'tracking_status' => 'active',
                'failed_reason' => null,
                'mt_shipment_id' => $shipmentId ?: $record->mt_shipment_id,
                'last_synced_at' => now(),
            ]);

            Log::info('ContainerTrackingService: pending tracking request now active', [
                'trip_id' => $record->trip_id,
                'shipment_id' => $record->mt_shipment_id,
            ]);

            if ($record->fresh()->mt_shipment_id) {
                SyncContainerMilestonesJob::dispatch($record->fresh());
            }

        } elseif ($status === 'failed') {
            $record->update([
                'tracking_status' => 'failed',
                'failed_reason' => $data['attributes']['failed_reason'] ?? 'Unknown',
            ]);

            Log::warning('ContainerTrackingService: tracking request failed on Kpler', [
                'trip_id' => $record->trip_id,
                'failed_reason' => $record->failed_reason,
            ]);
        }
    }

    // Shipment snapshot (webhook + nightly refresh)

    /**
     * Called by the webhook controller on `shipment_updated` events.
     * Also called by NightlyContainerSyncJob via refreshShipment().
     *
     * Responsibilities:
     *  - Update the TripContainerTracking snapshot
     *  - Sync vessel name / IMO / voyage / ETA / port info back to Trip
     *  - Auto-advance TripStatus from Kpler's transportationStatus
     *  - Create TripEvents for rollover and port-change alerts
     *  - Update ETA history, rollover history, transshipment ports
     */
    public function processWebhookShipment(array $shipment): void
    {
        $shipmentId = $shipment['shipmentId'] ?? $shipment['id'] ?? null;
        if (!$shipmentId) return;

        $record = TripContainerTracking::where('mt_shipment_id', $shipmentId)->first();
        if (!$record) {
            Log::warning('ContainerTrackingService: unknown shipmentId in webhook', [
                'shipment_id' => $shipmentId,
            ]);
            return;
        }

        $attrs = $shipment['attributes'] ?? [];
        $currentVessel = $attrs['currentVessel'] ?? null;
        $pos = $currentVessel['latestPosition'] ?? null;
        $insights = $attrs['insights'] ?? [];
        $pol = $attrs['portOfLoading'] ?? null;
        $pod = $attrs['portOfDischarge'] ?? null;
        $transshipmentPorts = $attrs['portsOfTransshipment'] ?? [];
        $transportationStatus = $attrs['transportationStatus'] ?? null;
        $rollovers = $insights['rollover'] ?? [];
        $positionTime = $pos['timestamp'] ?? $pos['positionReceivedAt'] ?? now();

        // ETA from POD planned arrival
        $podArrivalTimestamp = data_get($pod, 'arrivalDate.timestamp');
        $podArrivalStatus = data_get($pod, 'arrivalDate.status');

        $newEta = ($podArrivalStatus === 'planned' && $podArrivalTimestamp)
            ? $podArrivalTimestamp
            : null;
        $etaHistory = $this->buildUpdatedEtaHistory($record, $newEta);
        $wasRolled = $record->has_rollover;

        // Update TripContainerTracking snapshot
        $record->update([
            'tracking_status' => 'active',
            'transportation_status' => $transportationStatus,
            'arrival_delay_days' => $insights['arrivalDelayDays'] ?? null,
            'initial_carrier_eta' => $insights['initialCarrierEta'] ?? $record->initial_carrier_eta,
            'has_rollover' => !empty($rollovers),
            'pol_name' => data_get($pol, 'port.name') ?? $record->pol_name,
            'pol_unlocode' => data_get($pol, 'port.unlocode') ?? $record->pol_unlocode,
            'pod_name' => data_get($pod, 'port.name') ?? $record->pod_name,
            'pod_unlocode' => data_get($pod, 'port.unlocode') ?? $record->pod_unlocode,
            'current_vessel_name' => $currentVessel['name'] ?? $record->current_vessel_name,
            'current_vessel_imo' => $currentVessel['imo'] ?? $record->current_vessel_imo,
            'current_vessel_lat' => $pos['lat'] ?? $record->current_vessel_lat,
            'current_vessel_lng' => $pos['lon'] ?? $record->current_vessel_lng,
            'current_vessel_speed' => $pos['speed'] ?? $record->current_vessel_speed,
            'current_vessel_heading' => $pos['heading'] ?? $record->current_vessel_heading,
            'current_vessel_geo_area' => $pos['geographicalArea'] ?? $record->current_vessel_geo_area,
            'current_vessel_position_at' => $positionTime,
            'last_synced_at' => now(),
            'raw_shipment_snapshot' => $shipment,
            'eta_history' => $etaHistory,
            'rollover_history' => !empty($rollovers) ? $rollovers : $record->rollover_history,
            'transshipment_ports' => !empty($transshipmentPorts) ? $transshipmentPorts : $record->transshipment_ports,
        ]);

        $trip = $record->trip;
        if (!$trip) return;

        // Sync vessel + port info back to Trip (auto-fills what user had to enter manually)
        $tripUpdates = $this->buildTripUpdatesFromShipment($attrs, $trip);
        if (!empty($tripUpdates)) {
            $trip->updateQuietly($tripUpdates);
            $trip = $trip->fresh();
        }

        // Auto-advance TripStatus from coarse transportationStatus signal
        if ($transportationStatus) {
            $targetStatus = $this->transportationStatusToTripStatus($transportationStatus);
            if ($targetStatus && $trip->status->canTransitionTo($targetStatus)) {
                $this->advanceTripStatus($trip, $targetStatus, [
                    'triggered_by' => 'kpler_transportation_status',
                    'transportation_status' => $transportationStatus,
                ]);
                $trip = $trip->fresh();
            }
        }

        // Handle POD actual arrival → VesselArrived
        if ($podArrivalStatus === 'actual' && $podArrivalTimestamp) {
            if ($trip->status->canTransitionTo(TripStatus::VesselArrived)) {
                $this->advanceTripStatus($trip, TripStatus::VesselArrived, [
                    'triggered_by' => 'kpler_pod_actual_arrival',
                    'arrived_at' => $podArrivalTimestamp,
                ]);
                $trip = $trip->fresh();
            }
        }

        // Create TripEvents for vessel rollovers (only on first detection)
        if (!empty($rollovers) && !$wasRolled) {
            foreach ($rollovers as $rollover) {
                TripEvent::create([
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'vessel_rollover',
                    'previous_status' => null,
                    'new_status' => null,
                    'event_data' => $rollover,
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'created_at' => now(),
                ]);
            }

            Log::warning('ContainerTrackingService: vessel rollover detected', [
                'trip_id' => $trip->id,
                'rollover_count' => count($rollovers),
            ]);
        }

        Log::info('ContainerTrackingService: shipment snapshot updated', [
            'trip_id' => $record->trip_id,
            'shipment_id' => $shipmentId,
            'transportation_status' => $transportationStatus,
        ]);
    }

    // Nightly refresh (called by NightlyContainerSyncJob)

    /**
     * Fetch the latest shipment summary from Kpler and process it.
     * Used as a safety net for missed webhooks.
     */
    public function refreshShipment(TripContainerTracking $record): void
    {
        if (!$record->mt_shipment_id) return;

        $response = $this->http()->get("/shipments/{$record->mt_shipment_id}");

        if ($response->failed()) {
            Log::warning('ContainerTrackingService: refreshShipment fetch failed', [
                'trip_id' => $record->trip_id,
                'shipment_id' => $record->mt_shipment_id,
                'status' => $response->status(),
            ]);
            return;
        }

        $shipment = $response->json('data');
        if (!$shipment) return;

        $this->processWebhookShipment($shipment);
    }

    // Milestones

    /**
     * Fetch full transportation timeline from Kpler and sync milestones.
     * After upserting, derives the correct TripStatus from actual events
     * and advances the trip accordingly.
     */
    public function syncMilestones(TripContainerTracking $record): void
    {
        if (!$record->mt_shipment_id) return;

        $response = $this->http()
            ->get("/shipments/{$record->mt_shipment_id}/transportation-timeline");

        if ($response->failed()) {
            Log::warning('ContainerTrackingService: milestone fetch failed', [
                'shipment_id' => $record->mt_shipment_id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return;
        }

        $attrs = $response->json('data.attributes') ?? [];
        $locations = collect($attrs['locations'] ?? [])->keyBy('id');
        $vessels = collect($attrs['vessels'] ?? [])->keyBy('id');

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
                        'event_type' => $event['equipmentEventTypeName']
                            ?? $event['transportEventTypeName']
                                ?? 'unknown',
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
                        'occurred_at' => !empty($event['eventDateTime'])
                            ? Carbon::parse($event['eventDateTime'])
                            : null,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('ContainerTrackingService: failed to upsert milestone', [
                    'trip_id' => $record->trip_id,
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('ContainerTrackingService: milestones synced', [
            'trip_id' => $record->trip_id,
            'event_count' => count($allEvents),
        ]);

        // After all milestones are up to date, derive and apply the correct trip status
        $trip = $record->trip;
        if ($trip && !$trip->isLocked()) {
            $this->deriveAndAdvanceTripStatus($record->trip_id);
        }
    }

    // Status advancement from milestones

    /**
     * Process all actual milestones in sequence order and advance TripStatus
     * to the furthest valid state. Also applies customs_hold from inspection events.
     *
     * Idempotent — canTransitionTo() guards against invalid or backward transitions.
     */
    private function deriveAndAdvanceTripStatus(int $tripId): void
    {
        $trip = Trip::find($tripId);
        if (!$trip || $trip->isLocked()) return;

        $milestones = TripShipmentMilestone::where('trip_id', $tripId)
            ->where('event_classifier', 'actual')
            ->orderBy('sequence_order')
            ->orderBy('occurred_at')
            ->get();

        foreach ($milestones as $milestone) {
            $trip = $trip->fresh();

            $targetStatus = $this->milestoneToTripStatus($milestone);

            if ($targetStatus && $trip->status !== $targetStatus && $trip->status->canTransitionTo($targetStatus)) {
                $this->advanceTripStatus($trip, $targetStatus, [
                    'triggered_by' => 'marinetraffic_container_milestone',
                    'event_type' => $milestone->event_type,
                    'location_name' => $milestone->location_name,
                    'location_type' => $milestone->location_type,
                    'occurred_at' => $milestone->occurred_at?->toISOString(),
                ]);
            }
        }

        // Apply customs hold from the most recent customs event
        $trip = $trip->fresh();
        $latestCustomsEvent = $milestones
            ->filter(fn($m) => in_array($m->event_type, [
                'customs_selected_for_inspection',
                'customs_selected_for_scan',
                'customs_released',
            ]))
            ->last();

        if ($latestCustomsEvent) {
            $shouldHold = $latestCustomsEvent->event_type !== 'customs_released';
            if ($trip->customs_hold !== $shouldHold) {
                $trip->updateQuietly(['customs_hold' => $shouldHold]);

                Log::info('ContainerTrackingService: customs_hold updated from milestone', [
                    'trip_id' => $tripId,
                    'customs_hold' => $shouldHold,
                    'event_type' => $latestCustomsEvent->event_type,
                ]);
            }
        }
    }

    /**
     * Map a Kpler milestone event to our TripStatus.
     *
     * location_type values from Kpler:
     *   'port_of_Loading' (note capital L — match case-insensitively)
     *   'transshipment'
     *   'port_of_discharge'
     */
    private function milestoneToTripStatus(TripShipmentMilestone $milestone): ?TripStatus
    {
        $eventType = strtolower($milestone->event_type ?? '');
        $locationType = strtolower($milestone->location_type ?? '');

        return match (true) {
            // Container loaded onto the initial vessel at POL
            in_array($eventType, ['load', 'departure']) && str_contains($locationType, 'port_of_l')
            => TripStatus::OnVessel,

            // Vessel arrives at a transshipment port
            $eventType === 'arrival' && $locationType === 'transshipment'
            => TripStatus::InTransshipment,

            // Vessel departs transshipment — now on the next vessel leg
            $eventType === 'departure' && $locationType === 'transshipment'
            => TripStatus::OnVessel,

            // Vessel arrives at or container unloaded at POD
            in_array($eventType, ['arrival', 'unload']) && str_contains($locationType, 'port_of_d')
            => TripStatus::VesselArrived,

            default => null,
        };
    }

    /**
     * Map Kpler's coarse transportationStatus to our TripStatus.
     */
    private function transportationStatusToTripStatus(string $status): ?TripStatus
    {
        return match (strtolower($status)) {
            'in_transit' => TripStatus::OnVessel,
            'delivered', 'arrived' => TripStatus::VesselArrived,
            default => null,
        };
    }

    // Trip field sync

    /**
     * Build the array of Trip field updates from a Kpler shipment payload.
     * Only updates fields that Kpler has data for; never overwrites with nulls.
     * Never overwrites port codes if the user already set them (prevents Kpler
     * overwriting user corrections with stale data).
     */
    private function buildTripUpdatesFromShipment(array $attrs, Trip $trip): array
    {
        $updates = [];
        $currentVessel = $attrs['currentVessel'] ?? null;
        $pol = $attrs['portOfLoading'] ?? null;
        $pod = $attrs['portOfDischarge'] ?? null;

        // Vessel name — prefer currentVessel, fallback to loading vessel at POL
        $vesselName = $currentVessel['name']
            ?? data_get($pol, 'loadingVessel.name');
        if ($vesselName) {
            $updates['vessel_name'] = $vesselName;
        }

        // Vessel IMO
        $vesselImo = $currentVessel['imo']
            ?? data_get($pol, 'loadingVessel.imo');
        if ($vesselImo) {
            $updates['vessel_imo_number'] = (string)$vesselImo;
        }

        // Voyage number
        $voyageNumber = data_get($pol, 'voyageNumber');
        if ($voyageNumber) {
            $updates['voyage_number'] = $voyageNumber;
        }

        // ETD from POL departure
        $polDepartureTs = data_get($pol, 'departureDate.timestamp');
        if ($polDepartureTs) {
            $updates['etd'] = Carbon::parse($polDepartureTs);
        }

        // ETA from POD planned arrival (actual arrival triggers status change, not ETA update)
        $podArrivalStatus = data_get($pod, 'arrivalDate.status');
        $podArrivalTs = data_get($pod, 'arrivalDate.timestamp');
        if ($podArrivalStatus === 'planned' && $podArrivalTs) {
            $newEta = Carbon::parse($podArrivalTs);
            if (!$trip->eta || !$trip->eta->eq($newEta)) {
                $updates['eta'] = $newEta;
            }
        }

        // Origin port — only fill if not already set by user
        $polUnlocode = data_get($pol, 'port.unlocode');
        $polName = data_get($pol, 'port.name');
        if ($polUnlocode && !$trip->origin_port_code) {
            $updates['origin_port_code'] = $polUnlocode;
            $updates['origin_port_name'] = $polName;
        }

        // Destination port — only fill if not already set by user
        $podUnlocode = data_get($pod, 'port.unlocode');
        $podName = data_get($pod, 'port.name');
        if ($podUnlocode && !$trip->destination_port_code) {
            $updates['destination_port_code'] = $podUnlocode;
            $updates['destination_port_name'] = $podName;
        }

        return $updates;
    }

    // ETA history

    private function buildUpdatedEtaHistory(TripContainerTracking $record, ?string $newEta): ?array
    {
        if (!$newEta) return $record->eta_history;

        $history = $record->eta_history ?? [];
        $lastEntry = !empty($history) ? end($history) : null;

        // Skip if ETA hasn't changed
        if ($lastEntry && $lastEntry['eta'] === $newEta) {
            return $history;
        }

        $history[] = [
            'eta' => $newEta,
            'recorded_at' => now()->toISOString(),
        ];

        return $history;
    }

    // Shared status advancement helper

    /**
     * Advance a trip to a new status and log the event.
     * Re-fetches the trip before updating to guard against concurrent advancement.
     */
    private function advanceTripStatus(Trip $trip, TripStatus $newStatus, array $eventData = []): void
    {
        $trip = $trip->fresh();

        if ($trip->status === $newStatus) return;
        if (!$trip->status->canTransitionTo($newStatus)) return;

        $previousStatus = $trip->status;
        $trip->update(['status' => $newStatus]);

        TripEvent::create([
            'customer_id' => $trip->customer_id,
            'trip_id' => $trip->id,
            'event_type' => 'status_changed',
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'event_data' => $eventData,
            'actor_type' => 'system',
            'actor_id' => null,
            'created_at' => now(),
        ]);

        Log::info('ContainerTrackingService: trip status auto-advanced', [
            'trip_id' => $trip->id,
            'from' => $previousStatus->value,
            'to' => $newStatus->value,
            'triggered_by' => $eventData['triggered_by'] ?? 'kpler',
        ]);
    }
}
