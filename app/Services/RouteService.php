<?php

namespace App\Services;

use App\Enums\TripTransportationMode;
use App\Models\CustomerRoute;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RouteService
{
    // ── CRUD ────────────────────────────────────────────────────────────────────

    public function store(array $data, User $createdBy): CustomerRoute
    {
        $customerId = $createdBy->isPlatformUser()
            ? ($data['customer_id'] ?? null)
            : $createdBy->customer_id;

        abort_if(!$customerId, 400, 'customer_id is required for platform users.');

        if (empty($data['name'])) {
            $data['name'] = $this->generateName($data);
        }

        return CustomerRoute::create([
            ...$data,
            'customer_id'  => $customerId,
            'created_by_id' => $createdBy->id,
        ]);
    }

    public function update(CustomerRoute $route, array $data, User $updatedBy): CustomerRoute
    {
        $route->update($data);
        return $route->fresh();
    }

    public function toggleActive(CustomerRoute $route): CustomerRoute
    {
        $route->update(['is_active' => !$route->is_active]);
        return $route->fresh();
    }

    public function delete(CustomerRoute $route): void
    {
        $route->delete();
    }

    // ── Lane auto-promotion ─────────────────────────────────────────────────────

    /**
     * Determine whether a trip has enough data to create a meaningful route/lane,
     * based on transport mode.
     *
     * road:       dispatch_city + dispatch_state + delivery_city + delivery_state
     * sea:        origin_port_code + destination_port_code
     * multimodal: dispatch_city + destination_port_code OR origin_port_code + delivery_city
     */
    public function isRouteDataComplete(Trip $trip): bool
    {
        $mode = $trip->transport_mode instanceof TripTransportationMode
            ? $trip->transport_mode
            : TripTransportationMode::from($trip->transport_mode);

        return match ($mode) {
            TripTransportationMode::Road      => $this->isRoadComplete($trip),
            TripTransportationMode::Sea        => $this->isSeaComplete($trip),
            TripTransportationMode::Multimodal => $this->isMultimodalComplete($trip),
        };
    }

    private function isRoadComplete(Trip $trip): bool
    {
        return filled($trip->dispatch_city)
            && filled($trip->dispatch_state)
            && filled($trip->delivery_city)
            && filled($trip->delivery_state);
    }

    private function isSeaComplete(Trip $trip): bool
    {
        return filled($trip->origin_port_code)
            && filled($trip->destination_port_code);
    }

    private function isMultimodalComplete(Trip $trip): bool
    {
        return
            (filled($trip->dispatch_city) && filled($trip->destination_port_code))
            || (filled($trip->origin_port_code) && filled($trip->delivery_city));
    }

    /**
     * Conditionally promote a Trip to a named CustomerRoute.
     * Only creates a route if (a) the trip has complete data and
     * (b) no route has been created for this trip yet.
     *
     * The idempotency key is: name = "AUTO-{tripRef}".
     * This way re-running the same trigger on the same trip always hits
     * the same existing row.
     *
     * @return CustomerRoute|null  The route, or null if data wasn't complete.
     */
    public function promoteFromTrip(Trip $trip): ?CustomerRoute
    {
        if (!$this->isRouteDataComplete($trip)) {
            return null;
        }

        $routeData = $this->routeFieldsFromTrip($trip);
        $name = $routeData['name'];
        unset($routeData['name']);

        $route = CustomerRoute::firstOrCreate(
            ['customer_id' => $trip->customer_id, 'name' => $name],
            array_merge($routeData, [
                'customer_id' => $trip->customer_id,
                'is_active'   => true,
            ])
        );

        if ($route->wasRecentlyCreated) {
            Log::info('RouteService: route auto-promoted', [
                'trip_id'     => $trip->id,
                'route_id'    => $route->id,
                'route_name'  => $name,
            ]);
        }

        return $route;
    }

    /**
     * Backfill routes for all existing trips that qualify.
     * Used by php artisan trip:backfill-routes.
     *
     * @return array{created:int, skipped:int, errors:int}
     */
    public function backfillAll(): array
    {
        $trips = Trip::whereNotNull('transport_mode')
            ->orderBy('id')
            ->lazyById(500, 'id');

        $created = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($trips as $trip) {
            try {
                $route = $this->promoteFromTrip($trip);
                if ($route?->wasRecentlyCreated) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('RouteService: backfill error', [
                    'trip_id' => $trip->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    /** Extract route-relevant fields from a trip. */
    private function routeFieldsFromTrip(Trip $trip): array
    {
        $data = [
            'trip_type'   => $trip->trip_type?->value ?? $trip->trip_type,
            'transport_mode' => $trip->transport_mode instanceof TripTransportationMode
                ? $trip->transport_mode->value
                : $trip->transport_mode,
            'dispatch_location_name' => $trip->dispatch_location_name,
            'dispatch_address'        => $trip->dispatch_address,
            'dispatch_city'          => $trip->dispatch_city,
            'dispatch_state'         => $trip->dispatch_state,
            'dispatch_pincode'      => $trip->dispatch_pincode,
            'dispatch_country'      => $trip->dispatch_country,
            'dispatch_lat'          => $trip->dispatch_lat,
            'dispatch_lng'          => $trip->dispatch_lng,
            'delivery_location_name' => $trip->delivery_location_name,
            'delivery_address'       => $trip->delivery_address,
            'delivery_city'         => $trip->delivery_city,
            'delivery_state'        => $trip->delivery_state,
            'delivery_pincode'      => $trip->delivery_pincode,
            'delivery_country'      => $trip->delivery_country,
            'delivery_lat'          => $trip->delivery_lat,
            'delivery_lng'          => $trip->delivery_lng,
            'origin_port_name'       => $trip->origin_port_name,
            'origin_port_code'      => $trip->origin_port_code,
            'origin_port_category'   => $trip->origin_port_category?->value
                ?? $trip->origin_port_category,
            'destination_port_name'  => $trip->destination_port_name,
            'destination_port_code' => $trip->destination_port_code,
            'destination_port_category' => $trip->destination_port_category?->value
                ?? $trip->destination_port_category,
        ];

        $data['name'] = $this->generateName($data);

        // Prune nulls — DB defaults / nullable columns handle missing values.
        // This is the fix for the old SQLSTATE[23000] on dispatch_country :
        // firstOrCreate does NOT send missing keys, so MySQL uses its DEFAULT.
        return array_filter($data, fn($v) => $v !== null);
    }

    private function generateName(array $data): string
    {
        $mode = $data['transport_mode'] ?? 'road';

        if ($mode === TripTransportationMode::Sea->value) {
            $from = $data['origin_port_code']      ?? '?';
            $to   = $data['destination_port_code'] ?? '?';
            return trim("{$from} → {$to}");
        }

        if ($mode === TripTransportationMode::Multimodal->value) {
            $city = $data['dispatch_city']          ?? '?';
            $port = $data['destination_port_code']  ?? '?';
            return trim("{$city} → {$port}");
        }

        // road
        $city = $data['dispatch_city']  ?? '?';
        $dest = $data['delivery_city']   ?? '?';
        return trim("{$city} → {$dest}");
    }
}