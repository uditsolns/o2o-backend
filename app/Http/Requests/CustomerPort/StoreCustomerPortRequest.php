<?php

namespace App\Http\Requests\CustomerPort;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $customerId = $user->isPlatformUser()
            ? $this->input('customer_id')
            : $user->customer_id;

        return [
            'customer_id' => ['nullable', Rule::requiredIf($user->isPlatformUser()), 'integer', 'exists:customers,id'],
            'port_id' => [
                'required', 'integer', 'exists:ports,id',
                Rule::unique('customer_ports', 'port_id')
                    ->where('customer_id', $customerId),
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
