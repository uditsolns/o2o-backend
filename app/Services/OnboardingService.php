<?php

namespace App\Services;

use App\Enums\CustomerOnboardingStatus;
use App\Models\AuthorizedSignatory;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Port;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OnboardingService
{
    public function saveProfile(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
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

        return $customer->documents()->create([
            'uploaded_by_id' => $uploadedBy->id,
            'doc_type' => $data['doc_type'],
            'doc_number' => $data['doc_number'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'url' => $path,
        ]);
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

    public function submit(Customer $customer): Customer
    {
        $this->assertReadyToSubmit($customer);

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::Submitted,
        ]);

        return $customer->fresh();
    }

    private function assertReadyToSubmit(Customer $customer): void
    {
        $errors = [];

        $requiredFields = [
            'company_type', 'gst_number', 'pan_number', 'iec_number',
            'billing_address', 'billing_city', 'billing_state', 'billing_pincode',
        ];

        foreach ($requiredFields as $field) {
            if (empty($customer->$field)) {
                $errors[] = "Field '{$field}' is required before submission.";
            }
        }

        if ($customer->signatories()->count() === 0) {
            $errors[] = 'At least one authorized signatory is required.';
        }

        $requiredDocTypes = ['gst_cert', 'pan_card', 'iec_cert'];
        $uploaded = $customer->documents()->pluck('doc_type')->map(fn($t) => is_string($t) ? $t : $t->value)->all();

        foreach ($requiredDocTypes as $type) {
            if (!in_array($type, $uploaded, true)) {
                $errors[] = "Document '{$type}' is required before submission.";
            }
        }

        if (!empty($errors)) {
            abort(response()->json(['message' => 'Onboarding incomplete.', 'errors' => $errors], 422));
        }
    }
}
