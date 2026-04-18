<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRoad = $this->input('transport_mode') === 'road';

        return [
            'sequence' => ['required', 'integer', 'min:1'],
            'source_name' => ['required', 'string', 'max:255'],
            'destination_name' => ['required', 'string', 'max:255'],
            'transport_mode' => ['required', Rule::in(['road', 'sea'])],
            'tracking_source' => [
                Rule::requiredIf($isRoad),
                'nullable',
                Rule::in(['gps', 'tcl_tracker', 'e_lock', 'driver_mobile', 'driver_sim', 'fast_tag']),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
