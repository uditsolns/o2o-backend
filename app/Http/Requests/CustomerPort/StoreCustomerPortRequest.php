<?php

namespace App\Http\Requests\CustomerPort;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerPortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'port_id' => [
                'required', 'integer', 'exists:ports,id',
                // prevent duplicate — unique per customer
                \Illuminate\Validation\Rule::unique('customer_ports', 'port_id')
                    ->where('customer_id', $this->user()->customer_id),
            ],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'geo_fence_radius' => ['nullable', 'integer', 'min:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'port_id.unique' => 'This port has already been added to your account.',
        ];
    }
}
