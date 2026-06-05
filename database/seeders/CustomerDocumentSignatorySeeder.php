<?php

namespace Database\Seeders;

use App\Enums\CustomerDocType;
use App\Enums\CustomerOnboardingStatus;
use App\Enums\SepioStatus;
use App\Models\AuthorizedSignatory;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds AuthorizedSignatories and CustomerDocuments for all customers
 * that are past the Pending status (i.e., have gone through onboarding).
 *
 * Document types populated are aligned with the new Sepio-onboarding readiness rules:
 *   - Always:        gst_cert (required basic)
 *   - Sepio-enabled: pan_card, iec_cert, certificate_of_registration, self_stuffing_cert
 *   - Optional:      cha_auth_letter, tin, supporting
 */
class CustomerDocumentSignatorySeeder extends Seeder
{
    public function run(): void
    {
        // Only seed for customers that have progressed past Pending
        $customers = Customer::where('onboarding_status', '!=', CustomerOnboardingStatus::Pending->value)->get();

        $signatoryCount = 0;
        $documentCount = 0;

        foreach ($customers as $customer) {
            $uploader = User::where('customer_id', $customer->id)->first()
                ?? User::where('email', 'admin@admin.com')->first();

            // ── Signatories ───────────────────────────────────────────────────
            $signatories = $this->signatoryDefinitions($customer);
            foreach ($signatories as $sig) {
                AuthorizedSignatory::firstOrCreate(
                    ['customer_id' => $customer->id, 'name' => $sig['name']],
                    [
                        'designation' => $sig['designation'],
                        'id_proof_url' => null, // no actual file in seeder
                    ]
                );
                $signatoryCount++;
            }

            // ── Documents ─────────────────────────────────────────────────────
            $docs = $this->documentDefinitions($customer, $uploader);
            foreach ($docs as $doc) {
                CustomerDocument::firstOrCreate(
                    ['customer_id' => $customer->id, 'doc_type' => $doc['doc_type']],
                    [
                        'uploaded_by_id' => $uploader->id,
                        'doc_number' => $doc['doc_number'] ?? null,
                        'file_name' => $doc['file_name'],
                        'url' => $doc['url'],
                        'sepio_file_name' => $doc['sepio_file_name'] ?? null,
                        'sepio_rejection_reason' => $doc['sepio_rejection_reason'] ?? null,
                    ]
                );
                $documentCount++;
            }
        }

        $this->command->info("  CustomerDocumentSignatorySeeder: {$signatoryCount} signatories, {$documentCount} documents seeded.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function signatoryDefinitions(Customer $customer): array
    {
        return [
            [
                'name' => $customer->primary_contact_name ?? ($customer->first_name . ' ' . $customer->last_name),
                'designation' => 'Director',
            ],
            [
                'name' => 'Compliance Officer',
                'designation' => 'Company Secretary',
            ],
        ];
    }

    private function documentDefinitions(Customer $customer, User $uploader): array
    {
        $prefix = 'customers/' . $customer->id . '/documents';
        $sepioEnabled = (bool) $customer->sepio_enabled;
        $sepioVerified = $customer->sepio_status === SepioStatus::Verified->value;

        // PAN number — derive a synthetic placeholder from the GSTIN first 10 chars (or fallback)
        $syntheticPan = $customer->gst_number
            ? strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $customer->gst_number), 0, 10))
            : 'AAACX' . str_pad($customer->id, 5, '0', STR_PAD_LEFT) . 'X';

        $docs = [
            // ── Required basic ───────────────────────────────────────────────
            [
                'doc_type' => CustomerDocType::GstCrt->value,
                'doc_number' => $customer->gst_number,
                'file_name' => 'GST_Certificate.pdf',
                'url' => "{$prefix}/gst_cert.pdf",
                'sepio_file_name' => 'GST_' . $customer->id . '.pdf',
            ],

            // ── Sepio-required docs (only for Sepio-enabled customers) ──────
            [
                'doc_type' => CustomerDocType::PanCard->value,
                'doc_number' => $syntheticPan,
                'file_name' => 'PAN_Card.pdf',
                'url' => "{$prefix}/pan_card.pdf",
                'sepio_file_name' => $sepioEnabled ? 'PAN_' . $customer->id . '.pdf' : null,
            ],
            [
                'doc_type' => CustomerDocType::IecCert->value,
                'doc_number' => $customer->iec_number,
                'file_name' => 'IEC_Certificate.pdf',
                'url' => "{$prefix}/iec_cert.pdf",
                'sepio_file_name' => $sepioEnabled ? 'IEC_' . $customer->id . '.pdf' : null,
            ],
            [
                'doc_type' => CustomerDocType::CertificateOfRegistration->value,
                'file_name' => 'Certificate_Of_Incorporation.pdf',
                'url' => "{$prefix}/certificate_of_registration.pdf",
                'sepio_file_name' => $sepioEnabled ? 'COR_' . $customer->id . '.pdf' : null,
            ],
            [
                'doc_type' => CustomerDocType::SelfStuffingCert->value,
                'file_name' => 'Self_Stuffing_Declaration.pdf',
                'url' => "{$prefix}/self_stuffing_cert.pdf",
                'sepio_file_name' => $sepioEnabled && !$sepioVerified
                    // simulate a single Sepio-rejected doc for non-verified customers
                    ? null
                    : 'SELF_STUFF_' . $customer->id . '.pdf',
                'sepio_rejection_reason' => $sepioEnabled && !$sepioVerified
                    ? 'Document is not in PDF format. Please re-upload as a single PDF.'
                    : null,
            ],

            // ── Optional supporting docs (always seeded) ────────────────────
            [
                'doc_type' => CustomerDocType::Supporting->value,
                'file_name' => 'Board_Resolution.pdf',
                'url' => "{$prefix}/supporting.pdf",
            ],
        ];

        if ($sepioEnabled) {
            $docs[] = [
                'doc_type' => CustomerDocType::ChaAuthLetter->value,
                'file_name' => 'CHA_Authorization_Letter.pdf',
                'url' => "{$prefix}/cha_auth_letter.pdf",
                'sepio_file_name' => $sepioVerified ? 'CHA_' . $customer->id . '.pdf' : null,
            ];
        }

        return $docs;
    }
}
