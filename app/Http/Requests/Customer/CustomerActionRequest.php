<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CustomerActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $remarksRule = str_ends_with($this->route()->getName() ?? '', 'reject')
            ? ['required', 'string', 'max:2000']
            : ['nullable', 'string', 'max:2000'];

        return [
            'remarks' => $remarksRule,
            'remarks_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
