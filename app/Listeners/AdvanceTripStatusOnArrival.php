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

        // Guard: only advance if still in_transit
        if ($trip->status !== TripStatus::InTransit) return;

        $newStatus = match ($trip->transport_mode) {
            TripTransportationMode::Road => TripStatus::Delivered,
            TripTransportationMode::Multimodal => TripStatus::AtPort,
            default => null,
        };

        if (!$newStatus) return;

        // Idempotency: don't re-fire if already transitioned
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
                'location_name' => $event->point->location_name,
            ],
            'actor_type' => 'system',
            'actor_id' => null,
            'created_at' => now(),
        ]);

        Log::info('Trip auto-advanced via tracking geofence', [
            'trip_id' => $trip->id,
            'from' => $previous->value,
            'to' => $newStatus->value,
            'source' => $event->point->source,
        ]);
    }
}
