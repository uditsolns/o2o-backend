<?php

namespace App\Http\Requests\Route;

use App\Enums\{PortCategory, TripTransportationMode, TripType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mode = $this->input('transport_mode');
        $isRoad = in_array($mode, ['road', 'multimodal']);
        $isSea = in_array($mode, ['sea', 'multimodal']);

        return [
            'customer_id' => ['nullable', Rule::requiredIf($this->user()->isPlatformUser()), 'integer', 'exists:customers,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'trip_type' => ['required', Rule::enum(TripType::class)],
            'transport_mode' => ['required', Rule::enum(TripTransportationMode::class)],
            // Dispatch (required for road/multimodal)
            'dispatch_location_name' => ['nullable', 'string', 'max:255'],
            'dispatch_address' => [Rule::requiredIf($isRoad), 'nullable', 'string'],
            'dispatch_city' => [Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'dispatch_state' => [Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'dispatch_pincode' => ['nullable', 'string', 'max:10'],
            'dispatch_country' => ['nullable', 'string', 'max:100'],
            'dispatch_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'dispatch_lng' => ['nullable', 'numeric', 'between:-180,180'],
            // Delivery (required for road/multimodal)
            'delivery_location_name' => ['nullable', 'string', 'max:255'],
            'delivery_address' => [Rule::requiredIf($isRoad), 'nullable', 'string'],
            'delivery_city' => [Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'delivery_state' => [Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'delivery_pincode' => ['nullable', 'string', 'max:10'],
            'delivery_country' => ['nullable', 'string', 'max:100'],
            'delivery_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['nullable', 'numeric', 'between:-180,180'],
            // Origin port (required for sea/multimodal)
            'origin_port_name' => [Rule::requiredIf($isSea), 'nullable', 'string', 'max:255'],
            'origin_port_code' => [Rule::requiredIf($isSea), 'nullable', 'string', 'max:20'],
            'origin_port_category' => ['nullable', Rule::enum(PortCategory::class)],
            // Destination port (required for sea/multimodal)
            'destination_port_name' => [Rule::requiredIf($isSea), 'nullable', 'string', 'max:255'],
            'destination_port_code' => [Rule::requiredIf($isSea), 'nullable', 'string', 'max:20'],
            'destination_port_category' => ['nullable', Rule::enum(PortCategory::class)],
        ];
    }
}
