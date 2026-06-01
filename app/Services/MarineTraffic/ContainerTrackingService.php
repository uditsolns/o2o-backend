<?php

namespace App\Services\MarineTraffic;

use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\Trip;
use App\Models\TripContainerTracking;
use App\Models\TripEvent;
use App\Models\TripShipmentMilestone;
use App\Jobs\SyncContainerMilestonesJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    // Pending status check

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

        if ($status === 'shipment_created_successfully') {
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
                'shipment_id' => $record->fresh()->mt_shipment_id,
            ]);

            if ($record->fresh()->mt_shipment_id) {
                SyncContainerMilestonesJob::dispatch($record->fresh());
            }

        } elseif ($status === 'request_failed') {
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

    // Shipment snapshot

    public function processWebhookShipment(array $shipment, ?TripContainerTracking $knownRecord = null): void
    {
        $shipmentId = $shipment['shipmentId'] ?? $shipment['id'] ?? null;
        if (!$shipmentId) return;

        $record = $knownRecord
            ?? TripContainerTracking::where('mt_shipment_id', $shipmentId)->first();

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

        // POD dates
        $podArrivalTimestamp = data_get($pod, 'arrivalDate.timestamp');
        $podArrivalStatus = data_get($pod, 'arrivalDate.status');

        $newEta = ($podArrivalStatus === 'planned' && $podArrivalTimestamp)
            ? $podArrivalTimestamp
            : null;
        $etaHistory = $this->buildUpdatedEtaHistory($record, $newEta);
        $wasRolled = data_get($record->insights, 'has_rollover', false);

        // Backfill resolved SCAC (user may have entered 'AUTO')
        $resolvedScac = data_get($attrs, 'carrier.scac');
        if ($resolvedScac && $resolvedScac !== 'AUTO' && $resolvedScac !== $record->carrier_scac) {
            $record->trip?->updateQuietly(['carrier_scac' => $resolvedScac]);
        }

        // Normalize transshipment ports (lon → lng)
        $normalizedTransshipmentPorts = $this->normalizeTransshipmentPorts($transshipmentPorts);

        // Track when transportation_status changed
        $transportationStatusUpdatedAt = (
            $transportationStatus !== null &&
            $transportationStatus !== $record->transportation_status
        )
            ? now()
            : $record->transportation_status_updated_at;

        // Update all snapshot data
        $record->update([
            'tracking_status' => 'active',
            'transportation_status' => $transportationStatus,
            'transportation_status_updated_at' => $transportationStatusUpdatedAt,
            'is_routing_inconclusive' => ($transportationStatus === 'routing_data_inconclusive'),

            // Flat operational field — kept for VesselAisPollJob WHERE clause
            'current_vessel_imo' => $currentVessel['imo'] ?? $record->current_vessel_imo,

            // carrier JSON
            'carrier' => [
                'scac' => $resolvedScac ?? data_get($record->carrier, 'scac'),
                'name' => data_get($attrs, 'carrier.name') ?? data_get($record->carrier, 'name'),
            ],

            // container_specs JSON
            'container_specs' => [
                'iso_code' => data_get($attrs, 'containers.0.isoCode')
                    ?? data_get($record->container_specs, 'iso_code'),
                'type' => data_get($attrs, 'containers.0.type')
                    ?? data_get($record->container_specs, 'type'),
                'size' => data_get($attrs, 'containers.0.size')
                    ?? data_get($record->container_specs, 'size'),
            ],

            // pol JSON — Kpler uses 'lon', we normalize to 'lng'
            'pol' => [
                'name' => data_get($pol, 'port.name') ?? data_get($record->pol, 'name'),
                'unlocode' => data_get($pol, 'port.unlocode') ?? data_get($record->pol, 'unlocode'),
                'lat' => data_get($pol, 'port.lat') ?? data_get($record->pol, 'lat'),
                'lng' => data_get($pol, 'port.lon') ?? data_get($record->pol, 'lng'),
                'country' => data_get($pol, 'port.country') ?? data_get($record->pol, 'country'),
                'etd' => data_get($pol, 'departureDate.timestamp') ?? data_get($record->pol, 'etd'),
                'etd_status' => data_get($pol, 'departureDate.status') ?? null,
                'local_time_offset' => data_get($pol, 'departureDate.localTimeOffset') ?? null,
                'loading_vessel' => [
                    'name' => data_get($pol, 'loadingVessel.name') ?? data_get($record->pol, 'loading_vessel.name'),
                    'imo' => data_get($pol, 'loadingVessel.imo') ?? data_get($record->pol, 'loading_vessel.imo'),
                    'mmsi' => isset($pol['loadingVessel']['mmsi'])
                        ? (string)$pol['loadingVessel']['mmsi']
                        : data_get($record->pol, 'loading_vessel.mmsi'),
                    'mt_id' => data_get($pol, 'loadingVessel.mtId') ?? data_get($record->pol, 'loading_vessel.mt_id'),
                    'voyage' => data_get($pol, 'voyageNumber') ?? data_get($record->pol, 'loading_vessel.voyage'),
                ],
            ],

            // pod JSON
            'pod' => [
                'name' => data_get($pod, 'port.name') ?? data_get($record->pod, 'name'),
                'unlocode' => data_get($pod, 'port.unlocode') ?? data_get($record->pod, 'unlocode'),
                'lat' => data_get($pod, 'port.lat') ?? data_get($record->pod, 'lat'),
                'lng' => data_get($pod, 'port.lon') ?? data_get($record->pod, 'lng'),
                'country' => data_get($pod, 'port.country') ?? data_get($record->pod, 'country'),
                'arrival_status' => $podArrivalStatus ?? data_get($record->pod, 'arrival_status'),
                'arrival_at' => ($podArrivalStatus === 'actual' ? $podArrivalTimestamp : null)
                    ?? data_get($record->pod, 'arrival_at'),
            ],

            // current_vessel JSON — Kpler uses 'lon', we normalize to 'lng'
            'current_vessel' => [
                'name' => $currentVessel['name'] ?? data_get($record->current_vessel, 'name'),
                'imo' => $currentVessel['imo'] ?? data_get($record->current_vessel, 'imo'),
                'mmsi' => isset($currentVessel['mmsi'])
                    ? (string)$currentVessel['mmsi']
                    : data_get($record->current_vessel, 'mmsi'),
                'mt_id' => $currentVessel['mtId'] ?? data_get($record->current_vessel, 'mt_id'),
                'lat' => $pos['lat'] ?? data_get($record->current_vessel, 'lat'),
                'lng' => $pos['lon'] ?? data_get($record->current_vessel, 'lng'),
                'speed_knots' => $pos['speed'] ?? data_get($record->current_vessel, 'speed_knots'),
                'heading' => $pos['heading'] ?? data_get($record->current_vessel, 'heading'),
                'geo_area' => $pos['geographicalArea'] ?? data_get($record->current_vessel, 'geo_area'),
                'operational_status' => $currentVessel['operationalStatus']
                    ?? data_get($record->current_vessel, 'operational_status'),
                'position_at' => now()->toISOString(),
                // AIS-enriched fields — preserve values written by VesselAisPollJob
                'destination' => data_get($record->current_vessel, 'destination'),
                'current_port' => data_get($record->current_vessel, 'current_port'),
                'ais_eta' => data_get($record->current_vessel, 'ais_eta'),
            ],

            // insights JSON
            'insights' => [
                'arrival_delay_days' => $insights['arrivalDelayDays']
                    ?? data_get($record->insights, 'arrival_delay_days'),
                'initial_carrier_eta' => $insights['initialCarrierEta']
                    ?? data_get($record->insights, 'initial_carrier_eta'),
                'has_rollover' => !empty($rollovers),
            ],

            'pol_change_history' => !empty($insights['portOfLoadingChange'])
                ? $insights['portOfLoadingChange']
                : ($record->pol_change_history ?? []),

            'pod_change_history' => !empty($insights['portOfDischargeChange'])
                ? $insights['portOfDischargeChange']
                : ($record->pod_change_history ?? []),

            'last_synced_at' => now(),
            'raw_shipment_snapshot' => $shipment,
            'eta_history' => $etaHistory,
            'rollover_history' => !empty($rollovers) ? $rollovers : ($record->rollover_history ?? []),
            'transshipment_ports' => !empty($normalizedTransshipmentPorts)
                ? $normalizedTransshipmentPorts
                : ($record->transshipment_ports ?? []),
        ]);

        $trip = $record->trip;
        if (!$trip) return;

        // Sync relevant fields back to Trip
        $tripUpdates = $this->buildTripUpdatesFromShipment($attrs, $trip);
        if (!empty($tripUpdates)) {
            $trip->updateQuietly($tripUpdates);
            $trip = $trip->fresh();
        }

        // Transportation status → TripStatus advancement
        if ($transportationStatus) {
            $trip = $this->handleTransportationStatusChange(
                $trip,
                $transportationStatus
            );
        }

        // POD actual arrival — fire audit event only
        // Note: we no longer advance TripStatus here.
        // left_the_port_of_discharge drives Delivered/OutForDelivery.
        if ($podArrivalStatus === 'actual' && $podArrivalTimestamp) {
            $alreadyFired = TripEvent::where('trip_id', $trip->id)
                ->where('event_type', 'container_arrived_at_pod')
                ->exists();

            if (!$alreadyFired) {
                TripEvent::create([
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'container_arrived_at_pod',
                    'event_data' => ['arrived_at' => $podArrivalTimestamp],
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'created_at' => now(),
                ]);
            }
        }

        // Rollover events
        if (!empty($rollovers) && !$wasRolled) {
            foreach ($rollovers as $rollover) {
                TripEvent::create([
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'vessel_rollover',
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

        // Port change alert events
        if (!empty($insights['portOfLoadingChange'])) {
            TripEvent::create([
                'customer_id' => $trip->customer_id,
                'trip_id' => $trip->id,
                'event_type' => 'port_of_loading_changed',
                'event_data' => $insights['portOfLoadingChange'],
                'actor_type' => 'system',
                'actor_id' => null,
                'created_at' => now(),
            ]);
        }

        if (!empty($insights['portOfDischargeChange'])) {
            TripEvent::create([
                'customer_id' => $trip->customer_id,
                'trip_id' => $trip->id,
                'event_type' => 'port_of_discharge_changed',
                'event_data' => $insights['portOfDischargeChange'],
                'actor_type' => 'system',
                'actor_id' => null,
                'created_at' => now(),
            ]);
        }

        Log::info('ContainerTrackingService: shipment snapshot updated', [
            'trip_id' => $record->trip_id,
            'shipment_id' => $shipmentId,
            'transportation_status' => $transportationStatus,
        ]);
    }

    // Nightly refresh

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

        $this->processWebhookShipment($shipment, $record);
    }

    // Milestones

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

        $rows = [];
        $now = now();

        foreach ($allEvents as $event) {
            $eventId = $event['id'] ?? null;
            if (!$eventId) continue;

            try {
                $loc = $locations[$event['locationId'] ?? ''] ?? null;
                $vessel = $vessels[$event['vesselId'] ?? ''] ?? null;

                $rows[] = [
                    'trip_id' => $record->trip_id,
                    'customer_id' => $record->customer_id,
                    'mt_event_id' => $eventId,
                    'event_category' => isset($event['equipmentEventTypeName'])
                        ? 'equipment'
                        : 'transport',
                    'event_type' => $event['equipmentEventTypeName']
                        ?? $event['transportEventTypeName']
                            ?? 'unknown',
                    'event_classifier' => $event['eventClassifierCode'] ?? 'planned',
                    'mode_of_transport' => $event['modeOfTransport'] ?? null,
                    'equipment_indicator' => $event['equipmentEmptyIndicator'] ?? null,
                    'location_name' => $loc['name'] ?? null,
                    'location_unlocode' => $loc['unlocode'] ?? null,
                    'location_country' => $loc['country'] ?? null,
                    'location_lat' => $loc['lat'] ?? null,
                    // Kpler uses 'lon' — normalize to 'lng' on store
                    'location_lng' => $loc['lon'] ?? null,
                    'terminal_name' => $loc['terminal']['name'] ?? null,
                    'local_time_offset' => $loc['localTimeOffset'] ?? null,

                    // Normalize casing:
                    // "port_of_Loading" -> "port_of_loading"
                    // "transhipment_Port" -> "transhipment_port"
                    'location_type' => isset($loc['type'])
                        ? strtolower($loc['type'])
                        : null,
                    'vessel_name' => $vessel['name'] ?? null,
                    'vessel_imo' => $vessel['imo'] ?? null,
                    'vessel_mmsi' => isset($vessel['mmsi'])
                        ? (string)$vessel['mmsi']
                        : null,
                    'voyage_number' => $vessel['voyageNumber'] ?? null,
                    'vessel_operational_status' => $vessel['operationalStatus'] ?? null,
                    'sequence_order' => $event['eventOrder'] ?? 0,
                    'occurred_at' => !empty($event['eventDateTime'])
                        ? Carbon::parse($event['eventDateTime'])
                        : null,
                    'created_at' => $now,
                ];
            } catch (\Throwable $e) {
                Log::error('ContainerTrackingService: failed to prepare milestone row', [
                    'trip_id' => $record->trip_id,
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            DB::transaction(function () use ($record, $rows) {

                // Purge and reinsert — milestones are a pure Kpler-derived snapshot.
                // No user edits, safe to delete and recreate on every sync.
                // This prevents duplicate accumulation when Kpler rotates event IDs.
                TripShipmentMilestone::where('trip_id', $record->trip_id)->delete();

                if (empty($rows)) {
                    return;
                }

                // Chunk inserts for safety on very large timelines
                foreach (array_chunk($rows, 500) as $chunk) {
                    TripShipmentMilestone::insert($chunk);
                }
            });

        } catch (\Throwable $e) {

            Log::error('ContainerTrackingService: milestone sync transaction failed', [
                'trip_id' => $record->trip_id,
                'shipment_id' => $record->mt_shipment_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Log::info('ContainerTrackingService: milestones synced', [
            'trip_id' => $record->trip_id,
            'event_count' => count($rows),
        ]);

        $trip = $record->trip;
        if ($trip && !$trip->isLocked()) {
            $this->deriveAndAdvanceTripStatus($record->trip_id);
        }
    }

    // Status advancement from milestones

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

            if (
                $targetStatus
                && $trip->status !== $targetStatus
                && $trip->status->canTransitionTo($targetStatus)
            ) {
                $this->advanceTripStatus($trip, $targetStatus, [
                    'triggered_by' => 'marinetraffic_container_milestone',
                    'event_type' => $milestone->event_type,
                    'location_name' => $milestone->location_name,
                    'location_type' => $milestone->location_type,
                    'occurred_at' => $milestone->occurred_at?->toISOString(),
                ]);
            }
        }

        // Customs hold from latest customs event
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
            }
        }
    }

    /**
     * Map a milestone event to our TripStatus.
     * location_type values from Kpler (after strtolower normalization on ingest):
     *   port_of_loading, port_of_discharge, transhipment_port, via_port, inland_location
     * Note: "transhipment" is Kpler's spelling (single 's'), not "transshipment".
     */
    private function milestoneToTripStatus(TripShipmentMilestone $milestone): ?TripStatus
    {
        $eventType = strtolower($milestone->event_type ?? '');
        $locationType = strtolower($milestone->location_type ?? '');

        // Any load or departure event (at any location) → trip is Active
        if (in_array($eventType, ['load', 'departure'])) {
            return TripStatus::Active;
        }

        // Arrival or discharge at port_of_discharge → Delivered
        // locationType from docs: "port_of_discharge" (after lowercasing)
        if (
            in_array($eventType, ['arrival', 'discharge', 'unload'])
            && str_contains($locationType, 'port_of_d')
        ) {
            return TripStatus::Delivered;
        }

        return null;
    }

    // Transportation status handling

    /**
     * Handle transportationStatus changes with full Kpler enum awareness.
     * Returns the (potentially updated) trip model.
     */
    private function handleTransportationStatusChange(Trip $trip, string $status): Trip
    {
        // routing_data_inconclusive — fire event once, no status change
        if ($status === 'routing_data_inconclusive') {
            $alreadyFired = TripEvent::where('trip_id', $trip->id)
                ->where('event_type', 'routing_data_inconclusive')
                ->exists();

            if (!$alreadyFired) {
                TripEvent::create([
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'routing_data_inconclusive',
                    'event_data' => ['transportation_status' => $status],
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'created_at' => now(),
                ]);

                Log::warning('ContainerTrackingService: routing data inconclusive', [
                    'trip_id' => $trip->id,
                ]);
            }

            return $trip;
        }

        // left_the_port_of_discharge — mode-aware advancement
        if ($status === 'left_the_port_of_discharge') {
            $isMultimodal = $trip->transport_mode instanceof TripTransportationMode
                ? $trip->transport_mode === TripTransportationMode::Multimodal
                : $trip->transport_mode === TripTransportationMode::Multimodal->value;

            if ($isMultimodal && $trip->status->canTransitionTo(TripStatus::OutForDelivery)) {
                $this->advanceTripStatus($trip, TripStatus::OutForDelivery, [
                    'triggered_by' => 'kpler_left_port_of_discharge',
                    'transportation_status' => $status,
                ]);
                return $trip->fresh();
            }

            if (!$isMultimodal && $trip->status->canTransitionTo(TripStatus::Delivered)) {
                $this->advanceTripStatus($trip, TripStatus::Delivered, [
                    'triggered_by' => 'kpler_left_port_of_discharge',
                    'transportation_status' => $status,
                ]);
                return $trip->fresh();
            }

            return $trip;
        }

        // All other statuses
        $targetStatus = $this->mapTransportationStatusToTripStatus($status);

        if (!$targetStatus) {
            return $trip;
        }

        if (
            $targetStatus === TripStatus::Active
            && $trip->status->canTransitionTo(TripStatus::Active)
        ) {
            $this->advanceTripStatus($trip, TripStatus::Active, [
                'triggered_by' => 'kpler_transportation_status',
                'transportation_status' => $status,
            ]);
            return $trip->fresh();
        }

        if (
            $targetStatus === TripStatus::Delivered
            && $trip->status->canTransitionTo(TripStatus::Delivered)
        ) {
            $this->advanceTripStatus($trip, TripStatus::Delivered, [
                'triggered_by' => 'kpler_transportation_status',
                'transportation_status' => $status,
            ]);
            return $trip->fresh();
        }

        return $trip;
    }

    /**
     * Map full Kpler transportationStatus enum to TripStatus.
     * Returns null for statuses that need no advancement or special handling.
     */
    private function mapTransportationStatusToTripStatus(string $status): ?TripStatus
    {
        return match ($status) {
            // Pre-boarding — ensure trip is Active if still Draft
            'booked',
            'not_arrived_at_port_of_loading',
            'waiting_at_port_of_loading'
            => TripStatus::Active,

            // Active sea legs
            'underway_to_a_transhipment_port',
            'waiting_at_a_transhipment_port',
            'underway_to_port_of_discharge',
            'waiting_at_port_of_discharge'
            => TripStatus::Active,

            // Kpler marks journey complete — map to Delivered (ePOD still needed for Completed)
            'completed'
            => TripStatus::Delivered,

            // These are handled separately above, return null here
            'left_the_port_of_discharge',
            'routing_data_inconclusive'
            => null,

            default => null,
        };
    }

    /**
     * Whether AIS polling should be paused for this transportation status.
     * Container is not loaded on a moving vessel during these states.
     */
    public function shouldPauseAisPolling(?string $status): bool
    {
        if ($status === null) return false;

        return in_array($status, [
            'booked',
            'not_arrived_at_port_of_loading',
            'waiting_at_port_of_loading',
            'waiting_at_a_transhipment_port',
            'waiting_at_port_of_discharge',
            'left_the_port_of_discharge',
            'completed',
            'routing_data_inconclusive',
        ], true);
    }

    // Trip field sync

    private function buildTripUpdatesFromShipment(array $attrs, Trip $trip): array
    {
        $updates = [];
        $pol = $attrs['portOfLoading'] ?? null;
        $pod = $attrs['portOfDischarge'] ?? null;

        // ETD from POL departure
        $polDepartureTs = data_get($pol, 'departureDate.timestamp');
        if ($polDepartureTs) {
            $updates['etd'] = Carbon::parse($polDepartureTs);
        }

        // ETA from POD planned arrival
        $podArrivalStatus = data_get($pod, 'arrivalDate.status');
        $podArrivalTs = data_get($pod, 'arrivalDate.timestamp');
        if ($podArrivalStatus === 'planned' && $podArrivalTs) {
            $newEta = Carbon::parse($podArrivalTs);
            if (!$trip->eta || !$trip->eta->eq($newEta)) {
                $updates['eta'] = $newEta;
            }
        }

        // Origin port from POL
        $polUnlocode = data_get($pol, 'port.unlocode');
        $polName = data_get($pol, 'port.name');
        if ($polUnlocode) {
            $updates['origin_port_code'] = $polUnlocode;
            $updates['origin_port_name'] = $polName;
        }

        // Destination port from POD
        $podUnlocode = data_get($pod, 'port.unlocode');
        $podName = data_get($pod, 'port.name');
        if ($podUnlocode) {
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

        if ($lastEntry && $lastEntry['eta'] === $newEta) {
            return $history;
        }

        $history[] = [
            'eta' => $newEta,
            'recorded_at' => now()->toISOString(),
        ];

        return $history;
    }

    // Normalization helpers

    /**
     * Normalize transshipment ports: Kpler uses 'lon', we store as 'lng'.
     */
    private function normalizeTransshipmentPorts(array $ports): array
    {
        return array_map(function (array $entry) {
            if (isset($entry['port']['lon']) && !isset($entry['port']['lng'])) {
                $entry['port']['lng'] = $entry['port']['lon'];
                unset($entry['port']['lon']);
            }
            return $entry;
        }, $ports);
    }

    // Shared advancement helper

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
