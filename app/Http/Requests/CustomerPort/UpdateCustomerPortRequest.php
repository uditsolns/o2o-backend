<?php

namespace App\Http\Requests\CustomerPort;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerPortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'geo_fence_radius' => ['sometimes', 'integer', 'min:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
