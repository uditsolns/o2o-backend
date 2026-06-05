<?php

namespace App\Services;

use App\Enums\CustomerDocType;
use App\Enums\CustomerOnboardingStatus;
use App\Jobs\SepioUploadDocumentJob;
use App\Models\AuthorizedSignatory;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerLocation;
use App\Models\Port;
use App\Models\User;
use App\Services\Concerns\ChecksSepioReadiness;
use App\Services\Concerns\RecordsHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OnboardingService
{
    use RecordsHistory;
    use ChecksSepioReadiness;

    public function saveProfile(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        $customer = $customer->fresh();

        $this->upsertBillingLocation($customer);

        return $customer;
    }

    private function upsertBillingLocation(Customer $customer): void
    {
        if (
            empty($customer->billing_address) ||
            empty($customer->billing_city) ||
            empty($customer->billing_state) ||
            empty($customer->billing_pincode)
        ) {
            return;
        }

        $attributes = [
            'name' => $customer->company_name,
            'gst_number' => $customer->gst_number,
            'address' => $customer->billing_address,
            'landmark' => $customer->billing_landmark,
            'city' => $customer->billing_city,
            'state' => $customer->billing_state,
            'pincode' => $customer->billing_pincode,
            'country' => $customer->billing_country ?? 'India',
            'contact_person' => $customer->primary_contact_name,
            'contact_number' => $customer->primary_contact_mobile,
            'is_active' => true,
        ];

        CustomerLocation::updateOrCreate(
            ['customer_id' => $customer->id, 'name' => $customer->company_name],
            array_merge($attributes, ['customer_id' => $customer->id])
        );
    }

    public function addSignatory(Customer $customer, array $data): AuthorizedSignatory
    {
        return $customer->signatories()->create($data);
    }

    public function updateSignatory(AuthorizedSignatory $signatory, array $data): AuthorizedSignatory
    {
        $signatory->update($data);
        return $signatory->fresh();
    }

    public function deleteSignatory(AuthorizedSignatory $signatory): void
    {
        if ($signatory->id_proof_url) {
            Storage::delete($signatory->id_proof_url);
        }

        $signatory->delete();
    }

    public function uploadDocument(
        Customer     $customer,
        array        $data,
        UploadedFile $file,
        User         $uploadedBy
    ): CustomerDocument
    {
        $path = $file->store("customers/{$customer->id}/documents");

        $document = $customer->documents()->create([
            'uploaded_by_id' => $uploadedBy->id,
            'doc_type' => $data['doc_type'],
            'doc_number' => $data['doc_number'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'url' => $path,
        ]);

        if ($customer->sepio_company_id) {
            SepioUploadDocumentJob::dispatch($customer, $document);
        }

        return $document;
    }

    public function deleteDocument(CustomerDocument $document): void
    {
        Storage::delete($document->url);
        $document->delete();
    }

    public function savePorts(Customer $customer, array $portIds): void
    {
        $ports = Port::whereIn('id', $portIds)->where('is_active', true)->get();

        DB::transaction(function () use ($customer, $ports) {
            $customer->ports()->delete();

            $records = $ports->map(fn(Port $p) => [
                'customer_id' => $customer->id,
                'port_id' => $p->id,
                'port_category' => $p->port_category->value,
                'name' => $p->name,
                'code' => $p->code,
                'lat' => $p->lat,
                'lng' => $p->lng,
                'geo_fence_radius' => $p->geo_fence_radius,
                'is_active' => true,
                'created_at' => now(),
            ])->all();

            $customer->ports()->insert($records);
        });
    }

    public function submit(Customer $customer, User $by): Customer
    {
        // Must acknowledge rejection before resubmitting
        abort_if(
            $customer->onboarding_status === CustomerOnboardingStatus::IlRejected,
            422,
            'Please acknowledge the rejection before resubmitting. Use the acknowledge-rejection endpoint first.'
        );

        $this->assertReadyToSubmit($customer);

        $fromStatus = $customer->onboarding_status->value;

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::Submitted,
        ]);

        $this->recordOnboardingHistory(
            $customer->id,
            $fromStatus,
            CustomerOnboardingStatus::Submitted->value,
            'customer',
            $by->id,
            'Customer submitted onboarding for review.'
        );

        return $customer->fresh();
    }

    public function acknowledgeRejection(Customer $customer, User $by): Customer
    {
        abort_if(
            $customer->onboarding_status !== CustomerOnboardingStatus::IlRejected,
            422,
            'Only rejected onboarding can be acknowledged.'
        );

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::Pending,
            'il_remarks' => null,
        ]);

        $this->recordOnboardingHistory(
            $customer->id,
            CustomerOnboardingStatus::IlRejected->value,
            CustomerOnboardingStatus::Pending->value,
            'customer',
            $by->id,
            'Customer acknowledged rejection and reopened onboarding for editing.'
        );

        return $customer->fresh();
    }

    public function assertReadyToSubmit(Customer $customer): void
    {
        $errors = [];

        if ($customer->sepio_enabled) {
            // Sepio flow — delegate entirely to the shared trait so that the
            // submit, enable-sepio, and readiness-endpoint gates are identical.
            $customer->loadMissing('ports', 'locations', 'documents', 'signatories');
            $readiness = $this->buildSepioReadiness($customer);

            if (!$readiness['is_ready']) {
                $errors = $readiness['missing'];
            }
        } else {
            // Non-Sepio flow — basic gating only.
            $requiredFields = [
                'company_type',
                'gst_number',
                'billing_address',
                'billing_city',
                'billing_state',
                'billing_pincode',
            ];

            foreach ($requiredFields as $field) {
                if (empty($customer->$field)) {
                    $errors[] = "Field '{$field}' is required before submission.";
                }
            }

            if ($customer->signatories()->count() === 0) {
                $errors[] = 'At least one authorized signatory is required.';
            }

            $uploaded = $customer->documents()
                ->pluck('doc_type')
                ->map(fn($t) => is_string($t) ? $t : $t->value)
                ->all();

            foreach (CustomerDocType::required(false) as $type) {
                if (!in_array($type, $uploaded, true)) {
                    $errors[] = "Document '{$type}' is required before submission.";
                }
            }
        }

        if (!empty($errors)) {
            abort(response()->json([
                'message' => 'Onboarding incomplete.',
                'errors' => array_values(array_unique($errors)),
            ], 422));
        }
    }
}
