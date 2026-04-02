<?php

namespace App\Http\Requests\Wallet;

use App\Enums\WalletCoastingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'il_policy_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'il_policy_expiry' => ['sometimes', 'nullable', 'date'],
            'sum_insured' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'gwp' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'costing_type' => ['sometimes', Rule::enum(WalletCoastingType::class)],
            'credit_period' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'credit_capping' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'freight_rate_per_seal' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
