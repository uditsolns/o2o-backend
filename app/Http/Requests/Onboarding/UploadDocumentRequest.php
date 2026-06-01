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
        $docType = $this->input('doc_type');

        // These Sepio endpoints strictly require PDF
        $pdfOnlyTypes = [
            CustomerDocType::CertificateOfRegistration->value,
            CustomerDocType::SelfStuffingCert->value,
        ];

        $allowedMimes = in_array($docType, $pdfOnlyTypes, true)
            ? 'pdf'
            : 'pdf,jpg,jpeg,png';

        return [
            'doc_type' => ['required', Rule::enum(CustomerDocType::class)],
            'doc_number' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', "mimes:{$allowedMimes}", 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'This document type only accepts PDF files.',
        ];
    }
}
