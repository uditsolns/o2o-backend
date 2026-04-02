<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class VesselInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vessel_name' => ['required', 'string', 'max:255'],
            'vessel_imo_number' => ['nullable', 'string', 'max:20'],
            'voyage_number' => ['nullable', 'string', 'max:100'],
            'bill_of_lading' => ['nullable', 'string', 'max:100'],
            'eta' => ['nullable', 'date'],
            'etd' => ['nullable', 'date'],
        ];
    }
}
