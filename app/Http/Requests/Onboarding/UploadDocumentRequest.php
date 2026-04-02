<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\CustomerDocType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::enum(CustomerDocType::class)],
            'doc_number' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
