<?php

namespace App\Http\Resources;

use App\Models\TripTrackingPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripTrackingPoint */
class TripTrackingPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'accuracy' => $this->accuracy,
            'location_name' => $this->location_name,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
