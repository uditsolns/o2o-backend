<?php

namespace App\Http\Requests\Route;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'trip_type' => ['required', Rule::enum(TripType::class)],
            'transport_mode' => ['required', Rule::enum(TripTransportationMode::class)],
            'dispatch_location_id' => ['nullable', 'integer', 'exists:customer_locations,id'],
            'delivery_location_id' => ['nullable', 'integer', 'exists:customer_locations,id'],
            'origin_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'destination_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
