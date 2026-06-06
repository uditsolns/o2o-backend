<?php

namespace App\Http\Requests\Customer;

use App\Enums\CompanyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')->id;

        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('customers', 'email')->ignore($customerId)],
            'mobile' => ['sometimes', 'string', 'max:20'],

            // IEC / GST — format validated when present; never required by this
            // endpoint because the Sepio-specific gating already happens in
            // SaveProfileRequest and the ChecksSepioReadiness trait at submit time.
            'iec_number' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^IEC\d{7}$/i',
                Rule::unique('customers', 'iec_number')->ignore($customerId),
            ],
            'gst_number' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]$/i',
            ],
            'company_type' => ['sometimes', 'nullable', Rule::enum(CompanyType::class)],
            'industry_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_address' => ['sometimes', 'nullable', 'string'],
            'billing_landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_pincode' => ['sometimes', 'nullable', 'string', 'regex:/^\d{6}$/'],
            'billing_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'primary_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'primary_contact_email' => ['sometimes', 'nullable', 'email'],
            'primary_contact_mobile' => ['sometimes', 'nullable', 'string', 'regex:/^\d{10}$/'],
            'alternate_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alternate_contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'alternate_contact_email' => ['sometimes', 'nullable', 'email'],
        ];
    }
}
