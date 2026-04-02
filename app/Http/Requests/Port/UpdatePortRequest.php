<?php

namespace App\Http\Requests\Port;

use App\Enums\PortCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $portId = $this->route('port')->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('ports', 'code')->ignore($portId)],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'port_category' => ['sometimes', Rule::enum(PortCategory::class)],
            'sepio_id' => ['sometimes', 'nullable', 'integer'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'geo_fence_radius' => ['sometimes', 'nullable', 'integer', 'min:100'],
        ];
    }
}
