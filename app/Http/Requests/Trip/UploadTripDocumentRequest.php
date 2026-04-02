<?php

namespace App\Http\Requests\Trip;

use App\Enums\TripDocType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadTripDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::enum(TripDocType::class)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
