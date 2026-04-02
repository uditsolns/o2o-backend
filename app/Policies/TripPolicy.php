<?php

namespace App\Policies;

use App\Models\{Trip, User};

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('trip.view');
    }

    public function view(User $user, Trip $trip): bool
    {
        if (!$user->hasPermission('trip.view')) return false;
        if ($user->isPlatformUser()) return true;
        return $trip->customer_id === $user->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('trip.create');
    }

    public function update(User $user, Trip $trip): bool
    {
        if (!$user->hasPermission('trip.update')) return false;
        if ($trip->isLocked()) return false;
        if ($user->isPlatformUser()) return true;
        return $trip->customer_id === $user->customer_id;
    }

    public function complete(User $user, Trip $trip): bool
    {
        if (!$user->hasPermission('trip.complete')) return false;
        if ($user->isPlatformUser()) return true;
        return $trip->customer_id === $user->customer_id;
    }

    public function confirmDestination(User $user, Trip $trip): bool
    {
        if (!$user->hasPermission('trip.destination_confirm')) return false;
        if ($user->isPlatformUser()) return true;
        return $trip->customer_id === $user->customer_id;
    }

    // vessel-info update reuses trip.update
    public function addVesselInfo(User $user, Trip $trip): bool
    {
        return $this->update($user, $trip);
    }
}
