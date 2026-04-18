<?php

namespace App\Http\Requests\Route;

use App\Enums\{PortCategory, TripTransportationMode, TripType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trip_type' => ['sometimes', Rule::enum(TripType::class)],
            'transport_mode' => ['sometimes', Rule::enum(TripTransportationMode::class)],
            'dispatch_location_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dispatch_address' => ['sometimes', 'nullable', 'string'],
            'dispatch_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dispatch_state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dispatch_pincode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'dispatch_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dispatch_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'dispatch_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'delivery_location_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery_address' => ['sometimes', 'nullable', 'string'],
            'delivery_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_pincode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'delivery_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'origin_port_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'origin_port_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'origin_port_category' => ['sometimes', 'nullable', 'string', Rule::enum(PortCategory::class)],
            'destination_port_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'destination_port_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'destination_port_category' => ['sometimes', 'nullable', 'string', Rule::enum(PortCategory::class)],
        ];
    }
}
