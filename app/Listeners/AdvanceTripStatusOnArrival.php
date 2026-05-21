<?php

namespace App\Listeners;

use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Events\VehicleArrivedAtDestination;
use App\Models\TripEvent;
use Illuminate\Support\Facades\Log;

class AdvanceTripStatusOnArrival
{
    public function handle(VehicleArrivedAtDestination $event): void
    {
        $trip = $event->trip->fresh();

        if ($trip->status !== TripStatus::Active) return;

        $newStatus = match ($trip->transport_mode) {
            TripTransportationMode::Road, TripTransportationMode::Multimodal => TripStatus::Delivered,
            default => null,
        };

        if (!$newStatus) return;
        if (!$trip->status->canTransitionTo($newStatus)) return;

        $previous = $trip->status;
        $trip->update(['status' => $newStatus]);

        TripEvent::create([
            'customer_id' => $trip->customer_id,
            'trip_id' => $trip->id,
            'event_type' => 'status_changed',
            'previous_status' => $previous,
            'new_status' => $newStatus,
            'event_data' => [
                'triggered_by' => 'vehicle_tracking',
                'source' => $event->point->source,
                'lat' => $event->point->lat,
                'lng' => $event->point->lng,
            ],
            'actor_type' => 'system',
            'actor_id' => null,
            'created_at' => now(),
        ]);
    }
}
