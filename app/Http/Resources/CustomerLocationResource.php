<?php

namespace App\Http\Resources;

use App\Models\CustomerLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerLocation */
class CustomerLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_type' => $this->location_type,
            'name' => $this->name,
            'gst_number' => $this->gst_number,
            'address' => $this->address,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'country' => $this->country,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'is_active' => $this->is_active,
            'sepio_address_id' => $this->sepio_address_id,
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
