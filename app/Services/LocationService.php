<?php

namespace App\Services;

use App\Models\CustomerLocation;
use App\Models\User;

class LocationService
{
    public function store(array $data, User $createdBy): CustomerLocation
    {
        return CustomerLocation::create([
            ...$data,
            'customer_id' => $createdBy->customer_id,
            'created_by_id' => $createdBy->id,
        ]);
    }

    public function update(CustomerLocation $location, array $data): CustomerLocation
    {
        $location->update($data);
        return $location->fresh();
    }

    public function toggleActive(CustomerLocation $location): CustomerLocation
    {
        $location->update(['is_active' => !$location->is_active]);
        return $location->fresh();
    }

    public function delete(CustomerLocation $location): void
    {
        // Guard: reject if location is referenced by an active route
        $usedInRoute = $location->customer->routes()
            ->where(function ($q) use ($location) {
                $q->where('dispatch_location_id', $location->id)
                    ->orWhere('delivery_location_id', $location->id);
            })
            ->where('is_active', true)
            ->exists();

        if ($usedInRoute) {
            abort(422, 'Location is used in one or more active routes and cannot be deleted.');
        }

        $location->delete();
    }
}
