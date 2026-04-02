<?php

namespace App\Http\Requests\Location;

use App\Enums\LocationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_type' => ['required', Rule::enum(LocationType::class)],
            'name' => ['required', 'string', 'max:255'],
            'gst_number' => [
                Rule::requiredIf(fn() => in_array($this->location_type, ['billing', 'both'])),
                'nullable', 'string', 'max:20',
            ],
            'address' => ['nullable', 'string'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
