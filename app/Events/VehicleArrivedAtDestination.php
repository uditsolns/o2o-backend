<?php

namespace App\Events;

use App\Models\Trip;
use App\Models\TripTrackingPoint;
use Illuminate\Foundation\Events\Dispatchable;

class VehicleArrivedAtDestination
{
    use Dispatchable;

    public function __construct(
        public readonly Trip              $trip,
        public readonly TripTrackingPoint $point,
    )
    {
    }
}
