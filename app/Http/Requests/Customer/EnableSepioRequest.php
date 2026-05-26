<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class EnableSepioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by policy in controller
    }

    public function rules(): array
    {
        return [
            // No body needed — all validation happens in the service.
            // This request exists as a hook for future extension.
        ];
    }
}
