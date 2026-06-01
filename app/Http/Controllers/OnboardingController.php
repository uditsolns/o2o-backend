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
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $service)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $customer->load('signatories', 'documents', 'ports');

        $uploadedDocTypes = $customer->documents
            ->map(fn($d) => is_string($d->doc_type) ? $d->doc_type : $d->doc_type->value)
            ->all();

        // Pre-compute port categories once — used in both checklist and canSubmit
        $portCategories = $customer->ports
            ->map(fn($p) => is_string($p->port_category) ? $p->port_category : $p->port_category->value)
            ->all();

        $latestHistory = $customer->onboardingHistory()->first();

        return response()->json([
            'onboarding_status' => $customer->onboarding_status,
            'sepio_enabled' => $customer->sepio_enabled,
            'sepio_status' => $customer->sepio_status,
            'can_submit' => $this->canSubmit($customer, $portCategories),
            'customer' => new CustomerResource($customer),
            'signatories' => AuthorizedSignatoryResource::collection($customer->signatories),
            'documents' => CustomerDocumentResource::collection($customer->documents),
            'ports' => $customer->sepio_enabled ? $customer->ports : [],
            'latest_action' => $latestHistory ? [
                'from_status' => $latestHistory->from_status,
                'to_status' => $latestHistory->to_status,
                'actor_type' => $latestHistory->actor_type,
                'remarks' => $latestHistory->remarks,
                'remarks_file_url' => $latestHistory->remarks_file_url
                    ? Storage::temporaryUrl($latestHistory->remarks_file_url, now()->addMinutes(30))
                    : null,
                'created_at' => $latestHistory->created_at,
            ] : null,
            'checklist' => [
                'profile_complete' => $this->isProfileComplete($customer),
                'has_signatories' => $customer->signatories->isNotEmpty(),
                'required_docs' => CustomerDocType::required($customer->sepio_enabled),
                'uploaded_doc_types' => $uploadedDocTypes,
                // Separate port and ICD — both required for Sepio, always true for non-Sepio
                'has_port' => !$customer->sepio_enabled
                    || in_array('port', $portCategories, true),
                'has_icd' => !$customer->sepio_enabled
                    || in_array('icd', $portCategories, true),
            ],
        ]);
    }

    public function saveProfile(SaveProfileRequest $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $customer = $this->resolveCustomer($request);
        $customer = $this->service->saveProfile($customer, $request->validated());

        return response()->json(new CustomerResource($customer));
    }

    public function addSignatory(SignatoryRequest $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $customer = $this->resolveCustomer($request);
        $data = $request->safe()->except('id_proof');

        if ($request->hasFile('id_proof')) {
            $data['id_proof_url'] = $request->file('id_proof')
                ->store("customers/{$customer->id}/signatories");
        }

        $signatory = $this->service->addSignatory($customer, $data);

        return response()->json(new AuthorizedSignatoryResource($signatory), 201);
    }

    public function updateSignatory(SignatoryRequest $request, AuthorizedSignatory $signatory): JsonResponse
    {
        $this->denyIfSubmitted($request);
        $this->authorizeSignatory($request, $signatory);

        $data = $request->safe()->except('id_proof');

        if ($request->hasFile('id_proof')) {
            if ($signatory->id_proof_url) Storage::delete($signatory->id_proof_url);
            $data['id_proof_url'] = $request->file('id_proof')
                ->store("customers/{$signatory->customer_id}/signatories");
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

        $customer = $this->resolveCustomer($request);
        $document = $this->service->uploadDocument(
            $customer,
            $request->safe()->except('file'),
            $request->file('file'),
            $request->user()
        );

        return response()->json(new CustomerDocumentResource($document), 201);
    }

    public function deleteDocument(Request $request, CustomerDocument $document): JsonResponse
    {
        $this->denyIfSubmitted($request);
        $this->authorizeDocument($request, $document);
        $this->service->deleteDocument($document);

        return response()->json(['message' => 'Document removed.']);
    }

    public function savePorts(SavePortsRequest $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $customer = $this->resolveCustomer($request);
        $this->service->savePorts($customer, $request->validated('port_ids'));

        return response()->json(['message' => 'Ports saved.']);
    }

    public function submit(Request $request): JsonResponse
    {
        $this->denyIfSubmitted($request);

        $customer = $this->resolveCustomer($request);
        $customer = $this->service->submit($customer, $request->user());

        return response()->json([
            'message' => 'Onboarding submitted successfully.',
            'customer' => new CustomerResource($customer),
        ]);
    }

    public function acknowledgeRejection(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        // Client users can only acknowledge their own rejection.
        // Platform users can act on behalf of any customer.
        if ($request->user()->isClientUser()) {
            abort_if(
                $request->user()->customer_id !== $customer->id,
                403,
                'You can only acknowledge your own rejection.'
            );
        }

        $customer = $this->service->acknowledgeRejection($customer, $request->user());

        return response()->json([
            'message' => 'Rejection acknowledged. You may now update your details and resubmit.',
            'customer' => new CustomerResource($customer),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function denyIfSubmitted(Request $request): void
    {
        $user = $request->user();

        // Platform users can always act on behalf of customers
        if ($user->isPlatformUser()) return;

        $status = $user->customer?->onboarding_status;

        if ($status === CustomerOnboardingStatus::IlParked) {
            abort(403, 'Your account is currently on hold pending review by the platform team. You will be notified once a decision is made.');
        }

        $locked = [
            CustomerOnboardingStatus::Submitted,
            CustomerOnboardingStatus::IlApproved,
            CustomerOnboardingStatus::Completed,
        ];

        if (in_array($status, $locked, true)) {
            abort(403, 'Onboarding is already submitted and cannot be modified.');
        }
    }

    private function isProfileComplete(Customer $customer): bool
    {
        $required = [
            'company_type',
            'gst_number',
            'billing_address',
            'billing_city',
            'billing_state',
            'billing_pincode',
        ];

        if ($customer->sepio_enabled) {
            $required[] = 'iec_number';
        }

        foreach ($required as $field) {
            if (empty($customer->$field)) return false;
        }

        return true;
    }

    /**
     * @param array $portCategories Pre-computed from loaded ports relation.
     *                              Passed in to avoid re-iterating the collection.
     */
    private function canSubmit(Customer $customer, array $portCategories = []): bool
    {
        $uploadedDocTypes = $customer->documents
            ->map(fn($d) => is_string($d->doc_type) ? $d->doc_type : $d->doc_type->value)
            ->all();

        $hasRequiredDocs = empty(array_diff(
            CustomerDocType::required($customer->sepio_enabled),
            $uploadedDocTypes
        ));

        $hasSignatories = $customer->signatories->isNotEmpty();

        $hasPorts = true;
        if ($customer->sepio_enabled) {
            // If portCategories not passed (e.g. called outside status()),
            // compute it from the loaded relation
            if (empty($portCategories) && $customer->relationLoaded('ports')) {
                $portCategories = $customer->ports
                    ->map(fn($p) => is_string($p->port_category) ? $p->port_category : $p->port_category->value)
                    ->all();
            }

            $hasPorts = in_array('port', $portCategories, true)
                && in_array('icd', $portCategories, true);
        }

        return $this->isProfileComplete($customer)
            && $hasSignatories
            && $hasPorts
            && $hasRequiredDocs;
    }

    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();

        if ($user->isPlatformUser()) {
            $customerId = $request->input('customer_id');
            abort_if(!$customerId, 400, 'customer_id is required for platform users.');
            return Customer::findOrFail($customerId);
        }

        return $user->customer;
    }

    private function authorizeSignatory(Request $request, AuthorizedSignatory $signatory): void
    {
        $user = $request->user();
        if ($user->isPlatformUser()) return;
        if ($signatory->customer_id !== $user->customer_id) abort(403);
    }

    private function authorizeDocument(Request $request, CustomerDocument $document): void
    {
        $user = $request->user();
        if ($user->isPlatformUser()) return;
        if ($document->customer_id !== $user->customer_id) abort(403);
    }
}
