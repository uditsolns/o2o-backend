<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class SavePortsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'port_ids' => ['required', 'array', 'min:1'],
            'port_ids.*' => ['integer', 'exists:ports,id'],
        ];
    }
}
