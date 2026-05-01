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
            'order' => $this->whenLoaded('order', fn() => [
                'id' => $this->order->id,
                'order_ref' => $this->order->order_ref,
            ]),
            'trip' => new TripResource($this->whenLoaded('trip')),
            'customer' => $this->whenLoaded('customer', fn() => [
                'id' => $this->customer->id,
                'company_name' => $this->customer->company_name,
            ]),
            'status_logs' => SealStatusLogResource::collection($this->whenLoaded('statusLogs')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
