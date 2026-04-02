<?php

namespace App\Http\Requests\Route;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
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
            'name' => ['sometimes', 'string', 'max:255'],
            'trip_type' => ['sometimes', Rule::enum(TripType::class)],
            'transport_mode' => ['sometimes', Rule::enum(TripTransportationMode::class)],
            'dispatch_location_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_locations,id'],
            'delivery_location_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_locations,id'],
            'origin_port_id' => ['sometimes', 'nullable', 'integer', 'exists:ports,id'],
            'destination_port_id' => ['sometimes', 'nullable', 'integer', 'exists:ports,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
