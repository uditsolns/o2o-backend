<?php

namespace App\Services;

use App\Models\CustomerLocation;
use App\Models\CustomerRoute;
use App\Models\User;

class RouteService
{
    public function store(array $data, User $createdBy): CustomerRoute
    {
        $this->assertLocationsOwnedBy($data, $createdBy->customer_id);

        return CustomerRoute::create([
            ...$data,
            'customer_id' => $createdBy->customer_id,
            'created_by_id' => $createdBy->id,
        ]);
    }

    public function update(CustomerRoute $route, array $data, User $updatedBy): CustomerRoute
    {
        $this->assertLocationsOwnedBy($data, $updatedBy->customer_id);

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
     * Prevent a client user from referencing another tenant's locations.
     * TenantScope handles reads, but explicit IDs in write payloads need checking.
     */
    private function assertLocationsOwnedBy(array $data, int $customerId): void
    {
        $locationIds = array_filter([
            $data['dispatch_location_id'] ?? null,
            $data['delivery_location_id'] ?? null,
        ]);

        if (empty($locationIds)) return;

        $foreign = CustomerLocation::whereIn('id', $locationIds)
            ->where('customer_id', '!=', $customerId)
            ->exists();

        if ($foreign) {
            abort(403, 'One or more locations do not belong to your account.');
        }
    }
}
