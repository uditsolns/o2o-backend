<?php

namespace App\Http\Resources;

use App\Models\TripShipmentMilestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripShipmentMilestone */
class TripShipmentMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'event_classifier' => $this->event_classifier,
            'location' => [
                'name' => $this->location_name,
                'unlocode' => $this->location_unlocode,
                'country' => $this->location_country,
                'lat' => $this->location_lat,
                'lng' => $this->location_lng,
                'terminal' => $this->terminal_name,
                'type' => $this->location_type,
            ],
            'vessel' => [
                'name' => $this->vessel_name,
                'imo' => $this->vessel_imo,
                'voyage' => $this->voyage_number,
            ],
            'sequence_order' => $this->sequence_order,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
