<?php

namespace App\Http\Controllers;

use App\Enums\CustomerDocType;
use App\Enums\CustomerOnboardingStatus;
use App\Http\Requests\Onboarding\SavePortsRequest;
use App\Http\Requests\Onboarding\SaveProfileRequest;
use App\Http\Requests\Onboarding\SignatoryRequest;
use App\Http\Requests\Onboarding\UploadDocumentRequest;
use App\Http\Resources\AuthorizedSignatoryResource;
use App\Http\Resources\CustomerDocumentResource;
use App\Http\Resources\CustomerResource;
use App\Models\AuthorizedSignatory;
use App\Models\CustomerDocument;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Storage;

class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $service)
    {
    }

    /**
     * Current onboarding state — profile completeness, docs uploaded, ports selected.
     */
    public function status(Request $request): JsonResponse
    {
        abort_if($request->user()->isPlatformUser(), 400, 'This action is unauthorized.');

        $customer = $request->user()->customer->load(
            'signatories', 'documents', 'ports'
        );

        $uploadedDocTypes = $customer->documents
            ->map(fn($d) => is_string($d->doc_type) ? $d->doc_type : $d->doc_type->value)
            ->all();

        return response()->json([
            'onboarding_status' => $customer->onboarding_status,
            'can_submit' => $this->canSubmit($customer),
            'customer' => new CustomerResource($customer),
            'signatories' => AuthorizedSignatoryResource::collection($customer->signatories),
            'documents' => CustomerDocumentResource::collection($customer->documents),
            'ports' => $customer->ports,
            'checklist' => [
                'profile_complete' => $this->isProfileComplete($customer),
                'has_signatories' => $customer->signatories->isNotEmpty(),
                'required_docs' => CustomerDocType::required(),
                'uploaded_doc_types' => $uploadedDocTypes,
                'has_ports' => $customer->ports->isNotEmpty(),
            ],
        ]);
    }

    public function saveProfile(SaveProfileRequest $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $customer = $this->service->saveProfile(
            $request->user()->customer,
            $request->validated()
        );

        return response()->json(new CustomerResource($customer));
    }

    public function addSignatory(SignatoryRequest $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $data = $request->safe()->except('id_proof');

        if ($request->hasFile('id_proof')) {
            $path = $request->file('id_proof')->store(
                "customers/{$request->user()->customer_id}/signatories",
            );
            $data['id_proof_url'] = $path;
        }

        $signatory = $this->service->addSignatory(
            $request->user()->customer,
            $data
        );

        return response()->json(new AuthorizedSignatoryResource($signatory), 201);
    }

    public function updateSignatory(SignatoryRequest $request, AuthorizedSignatory $signatory): JsonResponse
    {
        $this->denyIfSubmitted($request);
        $this->authorizeSignatory($request, $signatory);

        $data = $request->safe()->except('id_proof');

        if ($request->hasFile('id_proof')) {
            if ($signatory->id_proof_url) {
                Storage::delete($signatory->id_proof_url);
            }
            $data['id_proof_url'] = $request->file('id_proof')->store(
                "customers/{$request->user()->customer_id}/signatories",
            );
        }

        $signatory = $this->service->updateSignatory($signatory, $data);

        return response()->json(new AuthorizedSignatoryResource($signatory));
    }

    public function deleteSignatory(Request $request, AuthorizedSignatory $signatory): JsonResponse
    {
        $this->denyIfSubmitted($request);
        $this->authorizeSignatory($request, $signatory);

        $this->service->deleteSignatory($signatory);

        return response()->json(['message' => 'Signatory removed.']);
    }

    public function uploadDocument(UploadDocumentRequest $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $document = $this->service->uploadDocument(
            $request->user()->customer,
            $request->safe()->except('file'),
            $request->file('file'),
            $request->user()
        );

        return response()->json(new CustomerDocumentResource($document), 201);
    }

    public function deleteDocument(Request $request, CustomerDocument $document): JsonResponse
    {
        $this->denyIfSubmitted($request);

        if ($document->customer_id !== $request->user()->customer_id) {
            abort(403);
        }

        $this->service->deleteDocument($document);

        return response()->json(['message' => 'Document removed.']);
    }

    public function savePorts(SavePortsRequest $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $this->service->savePorts(
            $request->user()->customer,
            $request->validated('port_ids')
        );

        return response()->json(['message' => 'Ports saved.']);
    }

    public function submit(Request $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $customer = $this->service->submit($request->user()->customer);

        return response()->json([
            'message' => 'Onboarding submitted successfully.',
            'customer' => new CustomerResource($customer),
        ]);
    }

    // Helpers

    private function denyIfSubmitted(Request $request): void
    {
        $status = $request->user()->customer?->onboarding_status;

        $locked = [
            CustomerOnboardingStatus::Submitted,
            CustomerOnboardingStatus::IlApproved,
            CustomerOnboardingStatus::Completed,
        ];

        if (in_array($status, $locked, true)) {
            abort(403, 'Onboarding is already submitted and cannot be modified.');
        }
    }

    private function authorizeSignatory(Request $request, AuthorizedSignatory $signatory): void
    {
        if ($signatory->customer_id !== $request->user()->customer_id) {
            abort(403);
        }
    }

    private function isProfileComplete(mixed $customer): bool
    {
        $required = [
            'company_type',
            'gst_number',
            'pan_number',
            'iec_number',
            'billing_address',
            'billing_city',
            'billing_state',
            'billing_pincode',
        ];

        foreach ($required as $field) {
            if (empty($customer->$field)) {
                return false;
            }
        }

        return true;
    }

    private function canSubmit(mixed $customer): bool
    {
        $uploadedDocTypes = $customer->documents
            ->map(fn($d) => is_string($d->doc_type) ? $d->doc_type : $d->doc_type->value)
            ->all();

        $hasRequiredDocs = empty(array_diff(
            CustomerDocType::required(),
            $uploadedDocTypes
        ));

        return $this->isProfileComplete($customer)
            && $customer->signatories->isNotEmpty()
            && $customer->ports->isNotEmpty()
            && $hasRequiredDocs;
    }
}
