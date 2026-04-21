<?php

namespace App\Http\Requests\Trip;

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
            'is_rc_verified' => ['sometimes', 'boolean'],
            'is_verification_done' => ['sometimes', 'boolean'],
            'rc_verification_payload' => ['nullable', 'json'],
            'container_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'container_type' => ['sometimes', 'nullable', 'string', 'max:20'],
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
            'eway_bill_number' => ['nullable', 'string', 'regex:/^\d{7,15}$/', 'max:20'],
            'eway_bill_validity_date' => ['nullable', 'date'],
            'dispatch_location_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dispatch_address' => ['sometimes', 'nullable', 'string'],
            'dispatch_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dispatch_state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dispatch_pincode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'dispatch_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dispatch_contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dispatch_contact_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'dispatch_contact_email' => ['sometimes', 'nullable', 'email'],
            'dispatch_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'dispatch_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'delivery_location_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery_address' => ['sometimes', 'nullable', 'string'],
            'delivery_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_pincode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'delivery_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery_contact_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'delivery_contact_email' => ['sometimes', 'nullable', 'email'],
            'delivery_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'origin_port_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'origin_port_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'origin_port_category' => ['sometimes', 'nullable', 'string', 'max:20'],
            'destination_port_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'destination_port_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'destination_port_category' => ['sometimes', 'nullable', 'string', 'max:20'],
            'dispatch_date' => ['sometimes', 'nullable', 'date'],
            'expected_delivery_date' => ['sometimes', 'nullable', 'date'],
            // Intermediate status transitions only (not in_transit or completed — those have dedicated endpoints)
            'status' => ['sometimes', Rule::in(['at_port', 'on_vessel', 'vessel_arrived', 'delivered'])],
        ];
    }
}
