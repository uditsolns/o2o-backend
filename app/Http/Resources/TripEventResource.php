<?php

namespace App\Http\Resources;

use App\Models\TripEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripEvent */
class TripEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'event_type' => $this->event_type,
            'previous_status' => $this->previous_status,
            'new_status' => $this->new_status,
            'event_data' => $this->event_data,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'created_at' => $this->created_at,

            'trip' => new TripResource($this->whenLoaded('trip')),
        ];
    }
}
