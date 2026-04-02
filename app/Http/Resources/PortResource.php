<?php

namespace App\Http\Resources;

use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Port */
class PortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'city' => $this->city,
            'country' => $this->country,
            'port_category' => $this->port_category,
            'sepio_id' => $this->sepio_id,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'geo_fence_radius' => $this->geo_fence_radius,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
