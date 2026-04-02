<?php

namespace App\Http\Requests\SealOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class SealOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $remarksRequired = str_ends_with($this->route()->getName() ?? '', 'reject');

        return [
            'remarks' => [$remarksRequired ? 'required' : 'nullable', 'string', 'max:2000'],
            'remarks_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
