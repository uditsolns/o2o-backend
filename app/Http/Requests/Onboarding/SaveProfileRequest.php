<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\CompanyType;
use App\Models\Customer;
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
        $user = $this->user();
        $customerId = $user->isClientUser() ? $user->customer_id : $this->input('customer_id');

        // Sepio-enabled customers must provide company_type, IEC, and GST.
        // Non-Sepio customers may skip them entirely; the fields stay optional.
        $isSepio = $this->resolveIsSepio($customerId);

        return [
            // Personal — required
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mobile' => ['sometimes', 'string', 'regex:/^\d{10}$/'],
            'email' => ['sometimes', 'email', Rule::unique('customers', 'email')->ignore($customerId)],

            // Company — required
            'company_name' => ['required', 'string', 'max:255'],
            'company_type' => [Rule::requiredIf($isSepio), 'nullable', Rule::enum(CompanyType::class)],

            // GST — required for Sepio customers, optional otherwise.
            // Regex still enforced when a value is provided.
            'gst_number' => [
                Rule::requiredIf($isSepio),
                'nullable',
                'string',
                'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]$/i',
            ],

            // IEC — required for Sepio customers, optional otherwise.
            'iec_number' => [
                Rule::requiredIf($isSepio),
                'nullable',
                'string',
                'regex:/^IEC\d{7}$/i',
                Rule::unique('customers', 'iec_number')->ignore($customerId),
            ],

            // Company — optional
            'industry_type' => ['sometimes', 'nullable', 'string', 'max:100'],

            // Billing address — required
            'billing_address' => ['required', 'string'],
            'billing_city' => ['required', 'string', 'max:100'],
            'billing_state' => ['required', 'string', 'max:100'],
            'billing_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_pincode' => ['sometimes', 'nullable', 'string', 'regex:/^\d{6}$/'],
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

    /**
     * Resolve whether the customer being edited has Sepio enabled.
     * Falls back to false when a customer cannot be located (e.g. fresh create flows
     * that don't have a customer yet — they'll start as non-Sepio by default).
     */
    private function resolveIsSepio(?int $customerId): bool
    {
        if (!$customerId) {
            return false;
        }

        return (bool) Customer::whereKey($customerId)->value('sepio_enabled');
    }
}
