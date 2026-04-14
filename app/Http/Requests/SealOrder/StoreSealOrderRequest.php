<?php

namespace App\Http\Requests\SealOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSealOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::requiredIf($this->user()->isPlatformUser()), 'integer', 'exists:customers,id'],
            'quantity' => ['required', 'integer', 'min:20'],
            'payment_type' => ['required', 'in:cash,credit,advance_balance'],
            'billing_location_id' => ['required', 'integer', 'exists:customer_locations,id'],
            'shipping_location_id' => ['required', 'integer', 'exists:customer_locations,id'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_contact' => ['nullable', 'string', 'max:20'],
            'port_ids' => ['required', 'array', 'min:1'],
            'port_ids.*' => ['integer', 'exists:customer_ports,id'],
        ];
    }
}
