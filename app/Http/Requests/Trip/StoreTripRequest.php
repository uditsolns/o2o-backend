<?php

namespace App\Http\Requests\Trip;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => ['nullable', 'integer', 'exists:customer_routes,id'],
            'trip_type' => ['required', Rule::enum(TripType::class)],
            'transport_mode' => ['required', Rule::enum(TripTransportationMode::class)],
            'seal_id' => ['nullable', 'integer', 'exists:seals,id'],
            // Driver
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_license' => ['nullable', 'string', 'max:50'],
            'driver_aadhaar' => ['nullable', 'string', 'max:20'],
            'driver_phone' => ['nullable', 'string', 'max:20'],
            // Vehicle
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['nullable', Rule::in(['truck', 'trailer', 'container_carrier'])],
            'transporter_name' => ['nullable', 'string', 'max:255'],
            'transporter_id' => ['nullable', 'string', 'max:100'],
            // Container
            'container_number' => ['nullable', 'string', 'max:50'],
            'container_type' => ['nullable', 'string', 'max:20'],
            'seal_issue_date' => ['nullable', 'date'],
            // Cargo
            'cargo_type' => ['nullable', 'string', 'max:100'],
            'cargo_description' => ['nullable', 'string'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'net_weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'quantity_unit' => ['nullable', 'string', 'max:50'],
            'declared_cargo_value' => ['nullable', 'numeric', 'min:0'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'eway_bill_number' => ['nullable', 'string', 'max:50'],
            'eway_bill_validity_date' => ['nullable', 'date'],
            // Dispatch location
            'dispatch_location_id' => ['nullable', 'integer', 'exists:customer_locations,id'],
            // Delivery location
            'delivery_location_id' => ['nullable', 'integer', 'exists:customer_locations,id'],
            // Origin port
            'origin_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            // Destination port
            'destination_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            // Timeline
            'dispatch_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:dispatch_date'],
        ];
    }
}
