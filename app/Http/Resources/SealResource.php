<?php

namespace App\Http\Resources;

use App\Models\Seal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Seal */
class SealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seal_number' => $this->seal_number,
            'status' => $this->status,
            'sepio_status' => $this->sepio_status,
            'last_scan_at' => $this->last_scan_at,
            'delivered_at' => $this->delivered_at,
            'trip_id' => $this->trip_id,
            'order' => $this->whenLoaded('order', fn() => [
                'id' => $this->order->id,
                'order_ref' => $this->order->order_ref,
            ]),
            'trip' => $this->whenLoaded('trip', fn() => [
                'id' => $this->trip->id,
                'trip_ref' => $this->trip->trip_ref,
                'status' => $this->trip->status,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
