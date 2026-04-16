<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\CompanyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->user()->customer_id;

        return [
            // Personal — required
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mobile' => ['sometimes', 'string', 'regex:/^\d{10}$/'],
            'email' => ['sometimes', 'email', Rule::unique('customers', 'email')->ignore($customerId)],

            // Company — required
            'company_name' => ['required', 'string', 'max:255'],
            'company_type' => ['required', Rule::enum(CompanyType::class)],
            'iec_number' => ['sometimes', 'string', 'regex:/^IEC\d{7}$/i',
                Rule::unique('customers', 'iec_number')->ignore($customerId)],

            // Company — optional
            'industry_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gst_number' => ['sometimes', 'nullable', 'string',
                'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]$/i'],
            'pan_number' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/i'],
            'cin_number' => ['sometimes', 'nullable', 'string', 'max:25'],
            'tin_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'cha_number' => ['sometimes', 'nullable', 'string', 'max:30'],

            // Billing address — required
            'billing_address' => ['required', 'string'],
            'billing_city' => ['required', 'string', 'max:100'],
            'billing_state' => ['required', 'string', 'max:100'],
            'billing_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_pincode' => ['sometimes', 'nullable', 'string', 'regex:/^\d{6}$/'],

            // Billing address — optional
            'billing_landmark' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Primary contact — required
            'primary_contact_name' => ['required', 'string', 'max:255'],
            'primary_contact_email' => ['required', 'email'],
            'primary_contact_mobile' => ['sometimes', 'nullable', 'string', 'regex:/^\d{10}$/'],

            // Alternate contact — optional
            'alternate_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alternate_contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'alternate_contact_email' => ['sometimes', 'nullable', 'email'],
        ];
    }
}
