<?php

namespace App\Http\Requests\Seal;

use Illuminate\Foundation\Http\FormRequest;

class IngestSealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seal_numbers' => ['required', 'array', 'min:1'],
            'seal_numbers.*' => ['string', 'max:100', 'distinct'],
            'dispatched_at' => ['required', 'date'],
        ];
    }
}
