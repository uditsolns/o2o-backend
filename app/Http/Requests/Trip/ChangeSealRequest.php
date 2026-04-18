<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seal_id' => ['required', 'integer', 'exists:seals,id'],
        ];
    }
}
