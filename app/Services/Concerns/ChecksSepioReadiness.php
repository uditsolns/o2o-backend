<?php

namespace App\Services\Concerns;

use App\Models\Customer;

trait ChecksSepioReadiness
{
    /**
     * Returns a structured readiness report.
     * Used by: CustomerService::getSepioReadiness(), assertReadyToSubmit(),
     *           canSubmit() (via service), and approve() safety net.
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

        $uploadedDocTypes = $customer->documents
            ->map(fn($d) => is_string($d->doc_type) ? $d->doc_type : $d->doc_type->value)
            ->all();

        $portCategories = $customer->ports
            ->map(fn($p) => is_string($p->port_category) ? $p->port_category : $p->port_category->value)
            ->all();

        $checks = [
            'iec_number' => [
                'met' => !empty($customer->iec_number),
                'message' => 'IEC number must be filled in the customer profile.',
            ],
            'port_assigned' => [
                'met' => in_array('port', $portCategories, true),
                'message' => 'At least one Port (port category) must be assigned.',
            ],
            'icd_assigned' => [
                'met' => in_array('icd', $portCategories, true),
                'message' => 'At least one ICD port must be assigned.',
            ],
            'iec_cert_uploaded' => [
                'met' => in_array('iec_cert', $uploadedDocTypes, true),
                'message' => 'IEC certificate document must be uploaded.',
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
        ];

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
