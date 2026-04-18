<?php

namespace App\Services;

use App\Models\CustomerRoute;
use App\Models\User;

class RouteService
{
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
            'customer_id' => $customerId,
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

    /**
     * Find a matching route or create one from trip data.
     * Match criteria: trip_type + transport_mode + key location identifiers.
     */
    public function findOrCreateFromTripData(int $customerId, array $tripData): CustomerRoute
    {
        $mode = $tripData['transport_mode'] ?? '';
        $isRoad = in_array($mode, ['road', 'multimodal']);
        $isSea = in_array($mode, ['sea', 'multimodal']);

        $query = CustomerRoute::where('customer_id', $customerId)
            ->where('trip_type', $tripData['trip_type'] ?? '')
            ->where('transport_mode', $mode);

        if ($isRoad) {
            $query->where('dispatch_city', $tripData['dispatch_city'] ?? null)
                ->where('dispatch_state', $tripData['dispatch_state'] ?? null)
                ->where('delivery_city', $tripData['delivery_city'] ?? null)
                ->where('delivery_state', $tripData['delivery_state'] ?? null);
        }

        if ($isSea) {
            $query->where('origin_port_code', $tripData['origin_port_code'] ?? null)
                ->where('destination_port_code', $tripData['destination_port_code'] ?? null);
        }

        $existing = $query->first();

        if ($existing) {
            return $existing;
        }

        $routeData = array_intersect_key($tripData, array_flip([
            'trip_type', 'transport_mode',
            'dispatch_location_name', 'dispatch_address', 'dispatch_city', 'dispatch_state',
            'dispatch_pincode', 'dispatch_country', 'dispatch_lat', 'dispatch_lng',
            'delivery_location_name', 'delivery_address', 'delivery_city', 'delivery_state',
            'delivery_pincode', 'delivery_country', 'delivery_lat', 'delivery_lng',
            'origin_port_name', 'origin_port_code', 'origin_port_category',
            'destination_port_name', 'destination_port_code', 'destination_port_category',
        ]));

        $routeData['name'] = $this->generateName($tripData);
        $routeData['customer_id'] = $customerId;
        $routeData['is_active'] = true;

        return CustomerRoute::create($routeData);
    }

    private function generateName(array $data): string
    {
        $mode = $data['transport_mode'] ?? 'road';

        if ($mode === 'sea') {
            return trim(($data['origin_port_code'] ?? '?') . ' → ' . ($data['destination_port_code'] ?? '?'));
        }

        if ($mode === 'multimodal') {
            return trim(($data['dispatch_city'] ?? '?') . ' → ' . ($data['destination_port_code'] ?? '?'));
        }

        // road
        return trim(($data['dispatch_city'] ?? '?') . ' → ' . ($data['delivery_city'] ?? '?'));
    }
}
