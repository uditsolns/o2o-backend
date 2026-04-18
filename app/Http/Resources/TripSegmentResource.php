<?php

namespace App\Http\Resources;

use App\Models\TripSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripSegment */
class TripSegmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'source_name' => $this->source_name,
            'destination_name' => $this->destination_name,
            'transport_mode' => $this->transport_mode,
            'tracking_source' => $this->tracking_source,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
