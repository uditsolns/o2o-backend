<?php

namespace App\Services;

use App\Jobs\SepioSyncLocationJob;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\User;

class LocationService
{
    public function store(array $data, User $createdBy): CustomerLocation
    {
        if ($createdBy->isPlatformUser()) {
            $customerId = $data['customer_id'] ?? null;
            abort_if(!$customerId, 400, 'customer_id is required for platform users.');
        } else {
            $customerId = $createdBy->customer_id;
            abort_if(!$customerId, 400, 'Customer context required.');
        }

        $location = CustomerLocation::create([
            ...$data,
            'customer_id' => $customerId,
            'created_by_id' => $createdBy->id,
        ]);

        $customer = Customer::find($customerId);

        if ($customer?->sepio_company_id) {
            SepioSyncLocationJob::dispatch($customer, $location);
        }

        return $location;
    }

    public function update(CustomerLocation $location, array $data): CustomerLocation
    {
        $location->update($data);
        $location = $location->fresh();

        $customer = $location->customer;

        if ($customer->sepio_company_id) {
            SepioSyncLocationJob::dispatch($customer, $location);
        }

        return $location;
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
