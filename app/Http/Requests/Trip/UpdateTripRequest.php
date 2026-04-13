<?php

namespace App\Http\Requests\Trip;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trip_type' => ['sometimes', Rule::enum(TripType::class)],
            'transport_mode' => ['sometimes', Rule::enum(TripTransportationMode::class)],
            'seal_id' => ['sometimes', 'nullable', 'integer', 'exists:seals,id'],
            'driver_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'driver_license' => ['sometimes', 'nullable', 'string', 'max:50'],
            'driver_aadhaar' => ['sometimes', 'nullable', 'string', 'max:20'],
            'driver_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_driver_license_verified' => ['sometimes', 'boolean'],
            'is_driver_aadhaar_verified' => ['sometimes', 'boolean'],
            'driver_license_verification_payload' => ['nullable', 'json'],
            'driver_aadhaar_verification_payload' => ['nullable', 'json'],
            'vehicle_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'vehicle_type' => ['sometimes', 'nullable', Rule::in(['truck', 'trailer', 'container_carrier'])],
            'transporter_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'transporter_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_rc_verified' => ['sometimes', 'boolean'],
            'is_verification_done' => ['sometimes', 'boolean'],
            'rc_verification_payload' => ['nullable', 'json'],
            'container_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'container_type' => ['sometimes', 'nullable', 'string', 'max:20'],
            'seal_issue_date' => ['sometimes', 'nullable', 'date'],
            'cargo_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cargo_description' => ['sometimes', 'nullable', 'string'],
            'hs_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'gross_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'net_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weight_unit' => ['sometimes', 'nullable', 'string', 'max:10'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'quantity_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'declared_cargo_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'invoice_date' => ['sometimes', 'nullable', 'date'],
            'eway_bill_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'eway_bill_validity_date' => ['sometimes', 'nullable', 'date'],
            'dispatch_location_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_locations,id'],
            'delivery_location_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_locations,id'],
            'origin_port_id' => ['sometimes', 'nullable', 'integer', 'exists:ports,id'],
            'destination_port_id' => ['sometimes', 'nullable', 'integer', 'exists:ports,id'],
            'dispatch_date' => ['sometimes', 'nullable', 'date'],
            'expected_delivery_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(['in_transit', 'at_port', 'on_vessel', 'vessel_arrived', 'delivered'])],
        ];
    }
}
