<?php

namespace App\Http\Resources;

use App\Models\CustomerPort;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerPort */
class CustomerPortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'port_id' => $this->port_id,
            'port_category' => $this->port_category,
            'name' => $this->name,
            'code' => $this->code,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'geo_fence_radius' => $this->geo_fence_radius,
            'is_active' => $this->is_active,
            'port' => $this->whenLoaded('port', fn() => new PortResource($this->port)),
            'created_at' => $this->created_at,
        ];
    }
}
