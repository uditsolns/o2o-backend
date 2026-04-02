<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:customers,email'],
            'mobile' => ['required', 'string', 'max:20'],
        ];
    }
}
