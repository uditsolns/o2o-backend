<?php

namespace App\Http\Resources;

use App\Models\SealOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin SealOrder */
class SealOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_ref' => $this->order_ref,
            'status' => $this->status,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'seal_cost' => $this->seal_cost,
            'freight_amount' => $this->freight_amount,
            'gst_amount' => $this->gst_amount,
            'total_amount' => $this->total_amount,
            'payment_type' => $this->payment_type,
            'receiver_name' => $this->receiver_name,
            'receiver_contact' => $this->receiver_contact,
            'il_remarks' => $this->il_remarks,
            'il_approved_at' => $this->il_approved_at,
            'sepio_order_id' => $this->sepio_order_id,
            'il_remark_file_url' => $this->when(
                filled($this->il_remark_file_url),
                fn() => Storage::temporaryUrl($this->il_remark_file_url, now()->addMinutes(30)),
            ),
            'courier_name' => $this->courier_name,
            'courier_docket_number' => $this->courier_docket_number,
            'seals_dispatched_at' => $this->seals_dispatched_at,
            'seals_delivered_at' => $this->seals_delivered_at,
            'billing_location' => $this->whenLoaded('billingLocation',
                fn() => new CustomerLocationResource($this->billingLocation)
            ),
            'shipping_location' => $this->whenLoaded('shippingLocation',
                fn() => new CustomerLocationResource($this->shippingLocation)
            ),
            'ordered_by' => $this->whenLoaded('orderedBy', fn() => [
                'id' => $this->orderedBy->id,
                'name' => $this->orderedBy->name,
            ]),
            'il_approved_by' => $this->whenLoaded('ilApprovedBy', fn() => [
                'id' => $this->ilApprovedBy->id,
                'name' => $this->ilApprovedBy->name,
            ]),
            'customer' => $this->whenLoaded('customer', fn() => [
                'id' => $this->customer->id,
                'company_name' => $this->customer->company_name,
            ]),
            'ordered_at' => $this->ordered_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
