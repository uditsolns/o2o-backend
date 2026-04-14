<?php

namespace App\Services;

use App\Models\CustomerLocation;
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

        $this->assertLocationsOwnedBy($data, $customerId);

        return CustomerRoute::create([
            ...$data,
            'customer_id' => $customerId,
            'created_by_id' => $createdBy->id,
        ]);
    }

    public function update(CustomerRoute $route, array $data, User $updatedBy): CustomerRoute
    {
        $customerId = $updatedBy->isPlatformUser()
            ? $route->customer_id
            : $updatedBy->customer_id;

        $this->assertLocationsOwnedBy($data, $customerId);

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
