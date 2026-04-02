<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class TopUpWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
