<?php

namespace App\Http\Resources;

use App\Models\CustomerRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerRoute */
class CustomerRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trip_type' => $this->trip_type,
            'transport_mode' => $this->transport_mode,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'dispatch_location' => $this->whenLoaded('dispatchLocation',
                fn() => new CustomerLocationResource($this->dispatchLocation)
            ),
            'delivery_location' => $this->whenLoaded('deliveryLocation',
                fn() => new CustomerLocationResource($this->deliveryLocation)
            ),
            'origin_port' => $this->whenLoaded('originPort',
                fn() => new PortResource($this->originPort)
            ),
            'destination_port' => $this->whenLoaded('destinationPort',
                fn() => new PortResource($this->destinationPort)
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
