<?php

namespace App\Http\Requests\Wallet;

use App\Enums\WalletCoastingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'costing_type' => ['required', Rule::enum(WalletCoastingType::class)],
            'credit_period' => ['nullable', 'integer', 'min:1', 'required_if:costing_type,credit'],
            'credit_capping' => ['nullable', 'numeric', 'min:0'],
            'freight_rate_per_seal' => ['nullable', 'numeric', 'min:0'],
            'low_balance_threshold' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
