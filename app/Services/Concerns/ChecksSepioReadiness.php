<?php

namespace App\Services\Concerns;

use App\Models\Customer;

trait ChecksSepioReadiness
{
    /**
     * Returns a structured readiness report for Sepio integration.
     *
     * Mirrors the requirements enforced by OnboardingController::canSubmit() and
     * OnboardingService::assertReadyToSubmit() so that the CustomerController's
     * sepio-readiness endpoint and the enable-Sepio flow apply the same gates.
     *
     * Used by: CustomerService::getSepioReadiness(), CustomerService::enableSepio(),
     *          CustomerService::approve() (safety net), and
     *          OnboardingService::assertReadyToSubmit() (sepio branch).
     */
    protected function buildSepioReadiness(Customer $customer): array
    {
        // Ensure relations are loaded — caller should eager-load but this is a safety net
        if (!$customer->relationLoaded('ports')) {
            $customer->load('ports');
        }
        if (!$customer->relationLoaded('locations')) {
            $customer->load('locations');
        }
        if (!$customer->relationLoaded('documents')) {
            $customer->load('documents');
        }
        if (!$customer->relationLoaded('signatories')) {
            $customer->load('signatories');
        }

        $uploadedDocTypes = $customer->documents
            ->map(fn($d) => is_string($d->doc_type) ? $d->doc_type : $d->doc_type->value)
            ->all();

        $portCategories = $customer->ports
            ->map(fn($p) => is_string($p->port_category) ? $p->port_category : $p->port_category->value)
            ->all();

        // Basic profile fields — mirrors OnboardingController::isProfileComplete().
        // Required for ALL customers; the OnboardingController treats them as a
        // prerequisite before sepio-specific fields are even evaluated.
        $basicProfileFields = [
            'company_type' => 'Company type must be set in the customer profile.',
            'gst_number' => 'GST number must be filled in the customer profile.',
            'billing_address' => 'Billing address must be filled in the customer profile.',
            'billing_city' => 'Billing city must be filled in the customer profile.',
            'billing_state' => 'Billing state must be filled in the customer profile.',
            'billing_pincode' => 'Billing pincode must be filled in the customer profile.',
        ];

        $profileChecks = [];
        foreach ($basicProfileFields as $field => $message) {
            $profileChecks["profile_{$field}"] = [
                'met' => !empty($customer->$field),
                'message' => $message,
            ];
        }

        $checks = array_merge($profileChecks, [
            'iec_number' => [
                'met' => !empty($customer->iec_number),
                'message' => 'IEC number must be filled in the customer profile.',
            ],
            'has_signatories' => [
                'met' => $customer->signatories->isNotEmpty(),
                'message' => 'At least one authorized signatory is required.',
            ],
            'port_assigned' => [
                'met' => in_array('port', $portCategories, true),
                'message' => 'At least one Port (port category) must be assigned.',
            ],
            'icd_assigned' => [
                'met' => in_array('icd', $portCategories, true),
                'message' => 'At least one ICD port must be assigned.',
            ],
            // Documents — must align with CustomerDocType::required(true):
            //   gst_cert (basic) + iec_cert, pan_card, certificate_of_registration,
            //   self_stuffing_cert (sepio).
            'gst_cert_uploaded' => [
                'met' => in_array('gst_cert', $uploadedDocTypes, true),
                'message' => 'GST certificate document must be uploaded.',
            ],
            'iec_cert_uploaded' => [
                'met' => in_array('iec_cert', $uploadedDocTypes, true),
                'message' => 'IEC certificate document must be uploaded.',
            ],
            'pan_card_uploaded' => [
                'met' => in_array('pan_card', $uploadedDocTypes, true),
                'message' => 'PAN card document must be uploaded.',
            ],
            'certificate_of_registration' => [
                'met' => in_array('certificate_of_registration', $uploadedDocTypes, true),
                'message' => 'Certificate of Registration must be uploaded (PDF only).',
            ],
            'self_stuffing_cert' => [
                'met' => in_array('self_stuffing_cert', $uploadedDocTypes, true),
                'message' => 'Self Stuffing Certificate must be uploaded (PDF only).',
            ],
            'billing_location' => [
                'met' => $customer->locations->where('is_active', true)->isNotEmpty(),
                'message' => 'At least one active billing location must exist.',
            ],
        ]);

        $isReady = collect($checks)->every(fn($c) => $c['met']);

        return [
            'is_ready' => $isReady,
            'checks' => $checks,
            'missing' => collect($checks)
                ->filter(fn($c) => !$c['met'])
                ->map(fn($c) => $c['message'])
                ->values()
                ->all(),
        ];
    }
}
