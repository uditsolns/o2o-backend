<?php

namespace App\Http\Requests\TripPricing;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.trip_type' => ['required', Rule::enum(TripType::class)],
            'rules.*.transport_mode' => ['required', Rule::enum(TripTransportationMode::class)],
            'rules.*.price_per_trip' => ['required', 'numeric', 'min:0'],
        ];
    }
}
