<?php

namespace App\Http\Requests\Port;

use App\Enums\PortCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:ports,code'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'port_category' => ['required', Rule::enum(PortCategory::class)],
            'sepio_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'geo_fence_radius' => ['nullable', 'integer', 'min:100'],
        ];
    }
}
