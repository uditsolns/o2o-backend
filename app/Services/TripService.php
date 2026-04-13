<?php

namespace App\Services;

use App\Enums\SealStatus;
use App\Enums\TripStatus;
use App\Models\CustomerLocation;
use App\Models\Port;
use App\Models\Seal;
use App\Models\Trip;
use App\Models\User;
use App\Services\Sepio\SepioSealService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

readonly class TripService
{
    public function __construct(
        private SealService      $sealService,
        private TripEventService $eventService,
        private SepioSealService $sepioSealService,
    )
    {
    }

    public function paginate(User $auth, array $filters = []): LengthAwarePaginator
    {
        return Trip::with('seal', 'createdBy')
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['trip_type']), fn($q) => $q->where('trip_type', $filters['trip_type']))
            ->latest()
            ->paginate(20);
    }

    public function store(array $data, User $createdBy): Trip
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $trip = Trip::create([
                ...$this->resolveSnapshots($data, $createdBy->customer_id),
                'customer_id' => $createdBy->customer_id,
                'created_by_id' => $createdBy->id,
                'trip_ref' => $this->generateTripRef(),
                'status' => TripStatus::Draft,
            ]);

            // Assign seal if provided
            if (!empty($data['seal_id'])) {
                $seal = Seal::findOrFail($data['seal_id']);
                $this->sealService->assignToTrip($seal, $trip->id);
                $trip->update(['seal_id' => $seal->id]);

                $this->eventService->log(
                    $trip, 'seal_assigned',
                    ['seal_number' => $seal->seal_number],
                    actorId: $createdBy->id
                );
            }

            $this->eventService->log(
                $trip, 'trip_created',
                ['trip_ref' => $trip->trip_ref],
                newStatus: TripStatus::Draft->value,
                actorId: $createdBy->id
            );

            return $trip->fresh();
        });
    }

    public function update(Trip $trip, array $data, User $updatedBy): Trip
    {
        return DB::transaction(function () use ($trip, $data, $updatedBy) {
            $previousStatus = $trip->status->value;
            $newStatus = isset($data['status']) ? $data['status'] : null;

            // Handle seal change
            if (array_key_exists('seal_id', $data)) {
                $this->handleSealChange($trip, $data['seal_id'], $updatedBy);
            }

            // Remove location/port IDs — they're resolved to snapshots
            $snapshots = $this->resolveSnapshots($data, $trip->customer_id);
            unset($snapshots['seal_id']); // handled separately

            $trip->update($snapshots);

            if ($newStatus === TripStatus::InTransit->value && $trip->seal && $trip->customer->sepio_company_id) {
                $this->sepioSealService->installSeal($trip->customer, $trip->fresh());
            }

            if ($newStatus && $newStatus !== $previousStatus) {
                $this->eventService->log(
                    $trip->fresh(), 'status_changed', [],
                    $previousStatus, $newStatus,
                    actorId: $updatedBy->id
                );
            }

            return $trip->fresh();
        });
    }

    public function addVesselInfo(Trip $trip, array $data, User $by): Trip
    {
        $trip->update([
            'vessel_name' => $data['vessel_name'],
            'vessel_imo_number' => $data['vessel_imo_number'] ?? null,
            'voyage_number' => $data['voyage_number'] ?? null,
            'bill_of_lading' => $data['bill_of_lading'] ?? null,
            'eta' => $data['eta'] ?? null,
            'etd' => $data['etd'] ?? null,
        ]);

        $this->eventService->log(
            $trip->fresh(), 'vessel_info_added',
            ['vessel_name' => $data['vessel_name'], 'imo' => $data['vessel_imo_number'] ?? null],
            actorId: $by->id
        );

        return $trip->fresh();
    }

    public function confirmDestination(Trip $trip, array $data, User $by): Trip
    {
        $previousStatus = $trip->status->value;

        $trip->update([
            'status' => TripStatus::Completed,
            'destination_confirmed_by_id' => $by->id,
            'destination_confirmed_at' => now(),
            'destination_confirmation_notes' => $data['notes'] ?? null,
            'actual_delivery_date' => $data['actual_delivery_date'] ?? today(),
            'trip_end_time' => now(),
            'epod_status' => 'completed',
            'epod_confirmed_at' => now(),
            'epod_confirmed_by_id' => $by->id,
        ]);

        // Mark seal as used
        if ($trip->seal) {
            $trip->seal->update(['status' => SealStatus::Used]);
        }

        $this->eventService->log(
            $trip->fresh(), 'destination_confirmed', [],
            $previousStatus, TripStatus::Completed->value,
            actorId: $by->id
        );

        return $trip->fresh();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve location/port IDs to snapshot columns.
     * Snapshots preserve the data at trip creation time for audit immutability.
     */
    private function resolveSnapshots(array $data, int $customerId): array
    {
        $resolved = $data;

        if (!empty($data['dispatch_location_id'])) {
            $loc = CustomerLocation::where('id', $data['dispatch_location_id'])
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $resolved = array_merge($resolved, [
                'dispatch_location_name' => $loc->name,
                'dispatch_address' => $loc->address,
                'dispatch_city' => $loc->city,
                'dispatch_state' => $loc->state,
                'dispatch_pincode' => $loc->pincode,
                'dispatch_country' => $loc->country,
                'dispatch_contact_person' => $loc->contact_person,
                'dispatch_contact_number' => $loc->contact_number,
                'dispatch_lat' => $loc->lat,
                'dispatch_lng' => $loc->lng,
            ]);
        }

        if (!empty($data['delivery_location_id'])) {
            $loc = CustomerLocation::where('id', $data['delivery_location_id'])
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $resolved = array_merge($resolved, [
                'delivery_location_name' => $loc->name,
                'delivery_address' => $loc->address,
                'delivery_city' => $loc->city,
                'delivery_state' => $loc->state,
                'delivery_pincode' => $loc->pincode,
                'delivery_country' => $loc->country,
                'delivery_contact_person' => $loc->contact_person,
                'delivery_contact_number' => $loc->contact_number,
                'delivery_lat' => $loc->lat,
                'delivery_lng' => $loc->lng,
            ]);
        }

        if (!empty($data['origin_port_id'])) {
            $port = Port::findOrFail($data['origin_port_id']);
            $resolved = array_merge($resolved, [
                'origin_port_name' => $port->name,
                'origin_port_code' => $port->code,
                'origin_port_category' => $port->port_category->value,
            ]);
        }

        if (!empty($data['destination_port_id'])) {
            $port = Port::findOrFail($data['destination_port_id']);
            $resolved = array_merge($resolved, [
                'destination_port_name' => $port->name,
                'destination_port_code' => $port->code,
                'destination_port_category' => $port->port_category->value,
            ]);
        }

        // Prefill from route if route_id provided and no explicit overrides
        if (!empty($data['route_id'])) {
            $this->prefillFromRoute($resolved, $data, $customerId);
        }

        // Strip virtual input IDs — not stored as columns
        foreach (['dispatch_location_id', 'delivery_location_id', 'origin_port_id', 'destination_port_id'] as $key) {
            unset($resolved[$key]);
        }

        return $resolved;
    }

    private function prefillFromRoute(array &$resolved, array $data, int $customerId): void
    {
        $route = \App\Models\CustomerRoute::where('id', $data['route_id'])
            ->where('customer_id', $customerId)
            ->first();

        if (!$route) return;

        // Only prefill if not explicitly overridden in the request
        if (empty($data['dispatch_location_id']) && $route->dispatch_location_id) {
            $loc = $route->dispatchLocation;
            if ($loc) {
                $resolved['dispatch_location_name'] = $loc->name;
                $resolved['dispatch_address'] = $loc->address;
                $resolved['dispatch_city'] = $loc->city;
                $resolved['dispatch_state'] = $loc->state;
                $resolved['dispatch_pincode'] = $loc->pincode;
                $resolved['dispatch_country'] = $loc->country;
                $resolved['dispatch_contact_person'] = $loc->contact_person;
                $resolved['dispatch_contact_number'] = $loc->contact_number;
                $resolved['dispatch_lat'] = $loc->lat;
                $resolved['dispatch_lng'] = $loc->lng;
            }
        }

        if (empty($data['delivery_location_id']) && $route->delivery_location_id) {
            $loc = $route->deliveryLocation;
            if ($loc) {
                $resolved['delivery_location_name'] = $loc->name;
                $resolved['delivery_address'] = $loc->address;
                $resolved['delivery_city'] = $loc->city;
                $resolved['delivery_state'] = $loc->state;
                $resolved['delivery_pincode'] = $loc->pincode;
                $resolved['delivery_country'] = $loc->country;
                $resolved['delivery_contact_person'] = $loc->contact_person;
                $resolved['delivery_contact_number'] = $loc->contact_number;
                $resolved['delivery_lat'] = $loc->lat;
                $resolved['delivery_lng'] = $loc->lng;
            }
        }

        if (empty($data['origin_port_id']) && $route->origin_port_id) {
            $port = $route->originPort;
            if ($port) {
                $resolved['origin_port_name'] = $port->name;
                $resolved['origin_port_code'] = $port->code;
                $resolved['origin_port_category'] = $port->port_category->value;
            }
        }

        if (empty($data['destination_port_id']) && $route->destination_port_id) {
            $port = $route->destinationPort;
            if ($port) {
                $resolved['destination_port_name'] = $port->name;
                $resolved['destination_port_code'] = $port->code;
                $resolved['destination_port_category'] = $port->port_category->value;
            }
        }

        // Prefill trip_type and transport_mode from route if not overridden
        if (empty($data['trip_type'])) $resolved['trip_type'] = $route->trip_type->value;
        if (empty($data['transport_mode'])) $resolved['transport_mode'] = $route->transport_mode->value;
    }

    private function handleSealChange(Trip $trip, ?int $newSealId, User $by): void
    {
        $currentSeal = $trip->seal;

        // Release old seal if different
        if ($currentSeal && $currentSeal->id !== $newSealId) {
            $this->sealService->releaseFromTrip($currentSeal);
        }

        // Assign new seal
        if ($newSealId && ($currentSeal?->id !== $newSealId)) {
            $seal = Seal::findOrFail($newSealId);
            $this->sealService->assignToTrip($seal, $trip->id);

            $this->eventService->log(
                $trip, 'seal_assigned',
                ['seal_number' => $seal->seal_number],
                actorId: $by->id
            );
        }

        // Detach (null seal_id)
        if (is_null($newSealId) && $currentSeal) {
            $this->sealService->releaseFromTrip($currentSeal);
            $trip->update(['seal_id' => null]);
        }
    }

    private function generateTripRef(): string
    {
        $last = Trip::lockForUpdate()->latest('id')->value('trip_ref');
        $next = $last ? (int)substr($last, 2) + 1 : 1;
        return 'TR' . str_pad($next, 7, '0', STR_PAD_LEFT);
    }
}
