<?php

namespace App\Http\Resources;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Trip */
class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_ref' => $this->trip_ref,
            'status' => $this->status,
            'trip_type' => $this->trip_type,
            'transport_mode' => $this->transport_mode,
            'risk_score' => $this->risk_score,
            // Driver
            'driver_name' => $this->driver_name,
            'driver_license' => $this->driver_license,
            'driver_phone' => $this->driver_phone,
            // Vehicle
            'vehicle_number' => $this->vehicle_number,
            'vehicle_type' => $this->vehicle_type,
            'tracking_token' => $this->when(
                $request->user()?->customer_id === $this->customer_id || $request->user()?->isPlatformUser(),
                $this->tracking_token
            ),
            'last_known_lat' => $this->last_known_lat,
            'last_known_lng' => $this->last_known_lng,
            'last_known_source' => $this->last_known_source,
            'last_tracked_at' => $this->last_tracked_at,
            // Container & seal
            'container_number' => $this->container_number,
            'container_type' => $this->container_type,
            'seal_issue_date' => $this->seal_issue_date,
            // Cargo
            'cargo_type' => $this->cargo_type,
            'cargo_description' => $this->cargo_description,
            'hs_code' => $this->hs_code,
            'gross_weight' => $this->gross_weight,
            'net_weight' => $this->net_weight,
            'weight_unit' => $this->weight_unit,
            'quantity' => $this->quantity,
            'quantity_unit' => $this->quantity_unit,
            'declared_cargo_value' => $this->declared_cargo_value,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date,
            'eway_bill_number' => $this->eway_bill_number,
            'eway_bill_validity_date' => $this->eway_bill_validity_date,
            // Dispatch
            'dispatch' => [
                'location_name' => $this->dispatch_location_name,
                'address' => $this->dispatch_address,
                'city' => $this->dispatch_city,
                'state' => $this->dispatch_state,
                'pincode' => $this->dispatch_pincode,
                'country' => $this->dispatch_country,
                'contact_person' => $this->dispatch_contact_person,
                'contact_number' => $this->dispatch_contact_number,
                'contact_email' => $this->dispatch_contact_email,
                'lat' => $this->dispatch_lat,
                'lng' => $this->dispatch_lng,
            ],
            // Delivery
            'delivery' => [
                'location_name' => $this->delivery_location_name,
                'address' => $this->delivery_address,
                'city' => $this->delivery_city,
                'state' => $this->delivery_state,
                'pincode' => $this->delivery_pincode,
                'country' => $this->delivery_country,
                'contact_person' => $this->delivery_contact_person,
                'contact_number' => $this->delivery_contact_number,
                'contact_email' => $this->delivery_contact_email,
                'lat' => $this->delivery_lat,
                'lng' => $this->delivery_lng,
            ],
            // Port snapshots
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
            // Vessel
            'vessel_name' => $this->vessel_name,
            'vessel_imo_number' => $this->vessel_imo_number,
            'voyage_number' => $this->voyage_number,
            'bill_of_lading' => $this->bill_of_lading,
            'eta' => $this->eta,
            'etd' => $this->etd,
            // Timeline
            'dispatch_date' => $this->dispatch_date,
            'trip_start_time' => $this->trip_start_time,
            'expected_delivery_date' => $this->expected_delivery_date,
            'actual_delivery_date' => $this->actual_delivery_date,
            'trip_end_time' => $this->trip_end_time,
            // ePOD
            'epod_status' => $this->epod_status,
            'epod_confirmed_at' => $this->epod_confirmed_at,
            'epod_confirmation_notes' => $this->epod_confirmation_notes,
            // Relations
            'seal' => $this->whenLoaded('seal', fn() => new SealResource($this->seal)),
            'created_by' => $this->whenLoaded('createdBy', fn() => ['id' => $this->createdBy->id, 'name' => $this->createdBy->name]),
            'documents' => $this->whenLoaded('documents', fn() => TripDocumentResource::collection($this->documents)),
            'segments' => $this->whenLoaded('segments', fn() => TripSegmentResource::collection($this->legs)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
