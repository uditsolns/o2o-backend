<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class SignatoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:100'],
            'id_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
