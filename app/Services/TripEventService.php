<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripEvent;

class TripEventService
{
    public function log(
        Trip    $trip,
        string  $eventType,
        array   $eventData = [],
        ?string $previousStatus = null,
        ?string $newStatus = null,
        string  $actorType = 'user',
        ?int    $actorId = null,
    ): TripEvent
    {
        return TripEvent::create([
            'customer_id' => $trip->customer_id,
            'trip_id' => $trip->id,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'event_data' => $eventData ?: null,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
        ]);
    }

    public function system(Trip $trip, string $eventType, array $eventData = []): TripEvent
    {
        return $this->log($trip, $eventType, $eventData, actorType: 'system');
    }
}
