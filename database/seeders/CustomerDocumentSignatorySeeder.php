<?php

namespace Database\Seeders;

use App\Models\AuthorizedSignatory;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds AuthorizedSignatories and CustomerDocuments for all customers
 * that are past the Pending status (i.e., have gone through onboarding).
 */
class CustomerDocumentSignatorySeeder extends Seeder
{
    public function run(): void
    {
        // Only seed for customers that have progressed past Pending
        $customers = Customer::where('onboarding_status', '!=', 'pending')->get();

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

        return [
            [
                'doc_type' => 'gst_cert',
                'doc_number' => $customer->gst_number,
                'file_name' => 'GST_Certificate.pdf',
                'url' => "{$prefix}/gst_cert.pdf",
                'sepio_file_name' => 'GST_' . $customer->id . '.pdf',
            ],
            [
                'doc_type' => 'pan_card',
                'doc_number' => $customer->pan_number,
                'file_name' => 'PAN_Card.pdf',
                'url' => "{$prefix}/pan_card.pdf",
                'sepio_file_name' => 'PAN_' . $customer->id . '.pdf',
            ],
            [
                'doc_type' => 'iec_cert',
                'doc_number' => $customer->iec_number,
                'file_name' => 'IEC_Certificate.pdf',
                'url' => "{$prefix}/iec_cert.pdf",
                'sepio_file_name' => 'IEC_' . $customer->id . '.pdf',
            ],
            [
                'doc_type' => 'supporting',
                'file_name' => 'Board_Resolution.pdf',
                'url' => "{$prefix}/supporting.pdf",
            ],
        ];
    }
}
