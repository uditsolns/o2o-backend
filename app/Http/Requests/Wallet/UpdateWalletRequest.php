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
            'costing_type' => ['sometimes', Rule::enum(WalletCoastingType::class)],
            'credit_period' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'credit_capping' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'freight_rate_per_seal' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'low_balance_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
