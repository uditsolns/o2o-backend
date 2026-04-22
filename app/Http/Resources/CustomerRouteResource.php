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
            'dispatch' => [
                'location_name' => $this->dispatch_location_name,
                'address' => $this->dispatch_address,
                'city' => $this->dispatch_city,
                'state' => $this->dispatch_state,
                'pincode' => $this->dispatch_pincode,
                'country' => $this->dispatch_country,
                'lat' => $this->dispatch_lat,
                'lng' => $this->dispatch_lng,
            ],
            'delivery' => [
                'location_name' => $this->delivery_location_name,
                'address' => $this->delivery_address,
                'city' => $this->delivery_city,
                'state' => $this->delivery_state,
                'pincode' => $this->delivery_pincode,
                'country' => $this->delivery_country,
                'lat' => $this->delivery_lat,
                'lng' => $this->delivery_lng,
            ],
            'origin_port' => [
                'name' => $this->origin_port_name,
                'code' => $this->origin_port_code,
                'category' => $this->origin_port_category,
            ],
            'destination_port' => [
                'name' => $this->destination_port_name,
                'code' => $this->destination_port_code,
                'category' => $this->destination_port_category,
            ],
            'customer' => $this->whenLoaded('customer', fn() => [
                'id' => $this->customer->id,
                'company_name' => $this->customer->company_name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
