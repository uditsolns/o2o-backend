<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

class StorePricingTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.min_quantity' => ['required', 'integer', 'min:1'],
            'tiers.*.max_quantity' => ['nullable', 'integer', 'gt:tiers.*.min_quantity'],
            'tiers.*.price_per_seal' => ['required', 'numeric', 'min:0'],
        ];
    }
}
