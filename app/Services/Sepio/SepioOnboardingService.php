<?php

namespace App\Services\Sepio;

use App\Enums\CustomerDocType;
use App\Exceptions\SepioException;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerLocation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Storage;

readonly class SepioOnboardingService
{
    public function __construct(private SepioClient $client)
    {
    }

    // Register Company
    public function registerCompany(Customer $customer): string
    {
        $ports = $customer->ports()->where('port_category', 'port')->get();
        $icds = $customer->ports()->where('port_category', 'icd')->get();
        $cfsItems = $customer->ports()->where('port_category', 'cfs')->get();

        if ($ports->isEmpty()) {
            throw new SepioException(
                'Cannot register with seal provider: customer has no ports assigned. Please add at least one port in onboarding.',
                null, 422
            );
        }

        if ($icds->isEmpty()) {
            throw new SepioException(
                'Cannot register with seal provider: customer has no ICD port assigned. Please add at least one ICD port in onboarding.',
                null, 422
            );
        }

        $plainPassword = Str::random(16);

        $payload = [
            'companydetailsInfo' => [
                'companyName' => $customer->company_name,
                'IEC' => $customer->iec_number,
                'sealRequest' => '1000',
                'port' => $this->formatPortList($ports),
                'icd' => $this->formatPortList($icds),
                'cfsLocation' => $this->formatPortList($cfsItems, first: true),
                'distributorId' => config('sepio.distributor_id'),
                'sepioURL' => 'sepio/companies',
            ],
            'primaryContactInfo' => [
                'fName' => $customer->first_name,
                'lName' => $customer->last_name,
                'email' => $customer->primary_contact_email ?? $customer->email,
                'contactNo' => preg_replace('/\D/', '', $customer->primary_contact_mobile ?? $customer->mobile),
                'password' => $plainPassword,
                'conpassword' => $plainPassword,
                'isAdmin' => true,
            ],
            'register_from_type' => config('sepio.register_from_type'),
        ];

        SepioPayloadValidator::registerCompany($payload);

        $response = $this->client->post('/registrationModule/registercompany', $payload);

        if ($response->failed() || empty($response->json('company_id'))) {
            $json = $response->json() ?? [];
            $msg = SepioException::extractMessage($json);

            // "user already exists" and "IEC already used" mean we're retrying a previously
            // registered company — treat as idempotent only if we already have sepio_company_id
            $isAlreadyExists = str_contains(strtolower($msg), 'already exists')
                || str_contains(strtolower($msg), 'already used');

            if ($isAlreadyExists && $customer->sepio_company_id) {
                Log::info('Sepio registerCompany skipped — already registered', ['customer_id' => $customer->id]);
                return $customer->sepio_company_id;
            }

            throw SepioException::fromResponse($json, 'Sepio company registration failed: ' . $msg);
        }

        $sepioCompanyId = (string)$response->json('company_id');

        $customer->update([
            'sepio_company_id' => $sepioCompanyId,
            'sepio_credentials' => [
                'email' => $customer->primary_contact_email ?? $customer->email,
                'password' => $plainPassword,
            ],
        ]);

        Log::info('Sepio company registered', [
            'customer_id' => $customer->id,
            'sepio_company_id' => $sepioCompanyId,
        ]);

        return $sepioCompanyId;
    }

    // Sync all locations
    public function syncAllLocations(Customer $customer): void
    {
        foreach ($customer->locations()->where('is_active', true)->get() as $location) {
            try {
                $this->syncLocation($customer, $location);
            } catch (\Throwable $e) {
                Log::error('Sepio syncLocation failed for location', [
                    'customer_id' => $customer->id,
                    'location_id' => $location->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Push a location to Sepio as BOTH billing and shipping address.
     */
    public function syncLocation(Customer $customer, CustomerLocation $location): void
    {
        $payload = [
            'createdBy' => $customer->primary_contact_email ?? $customer->email,
            'companyId' => $customer->sepio_company_id,
            'billingAddressInfo' => [
                'billAddresses' => [[
                    'billingCompanyName' => $customer->company_name,
                    'address' => $location->address,
                    'landmark' => $location->landmark ?? '',
                    'zipcode' => $location->pincode,
                    'city' => $location->city,
                    'state' => strtoupper($location->state),
                    'gstno' => $location->gst_number ?? $customer->gst_number ?? '',
                ]],
            ],
            'shippingAddressInfo' => [
                'addresses' => [[
                    'address' => $location->address,
                    'landmark' => $location->landmark ?? '',
                    'city' => $location->city,
                    'state' => strtoupper($location->state),
                    'zipcode' => $location->pincode,
                ]],
            ],
            'fclFlag' => 1,
            'cfsFlag' => 0,
            'warehouseFlag' => 0,
        ];

        SepioPayloadValidator::updateAddress($payload);

        $response = $this->client->postAs($customer, '/registrationModule/updateaddress', $payload);

        if ($response->failed()) {
            Log::error('Sepio syncLocation failed', [
                'customer_id' => $customer->id,
                'location_id' => $location->id,
                'response' => $response->json(),
            ]);
            return;
        }

        Log::debug('Sepio address updated', $response->json());

        $this->pullAndStoreAddressId($customer, $location);
    }

    // Upload all existing documents

    public function uploadAllDocuments(Customer $customer): void
    {
        foreach ($customer->documents as $document) {
            try {
                $this->uploadDocument($customer, $document);
            } catch (\Throwable $e) {
                $this->rejectDocument(
                    $document,
                    $e->getMessage(),
                    context: ['source' => 'exception']
                );
            }
        }
    }

    /**
     * Persist a Sepio-side rejection on the document and log it.
     * Cleared automatically by CustomerService::retrySepioRegistration()
     * once the customer re-uploads or the platform retries.
     */
    private function rejectDocument(
        CustomerDocument $document,
        string $reason,
        array $context = []
    ): void {
        $document->update(['sepio_rejection_reason' => $reason]);

        Log::warning('Sepio KYC document rejected', array_merge([
            'doc_id' => $document->id,
            'customer_id' => $document->customer_id,
            'doc_type' => is_string($document->doc_type) ? $document->doc_type : $document->doc_type->value,
            'reason' => $reason,
        ], $context));
    }

    public function uploadDocument(Customer $customer, CustomerDocument $document): void
    {
        $docType = $document->doc_type;

        $mapping = $this->docTypeMapping($docType);
        if (!$mapping) return;

        // Sepio-side PDF-only enforcement
        $pdfOnlyTypes = [
            CustomerDocType::CertificateOfRegistration,
            CustomerDocType::SelfStuffingCert,
        ];

        $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));

        if (in_array($docType, $pdfOnlyTypes, true) && $extension !== 'pdf') {
            $this->rejectDocument(
                $document,
                'Sepio requires this document type to be a PDF.',
                ['extension' => $extension]
            );
            return;
        }

        $fileContents = Storage::get($document->url);

        if (!$fileContents) {
            $this->rejectDocument($document, 'File is missing from storage and could not be uploaded to Sepio.');
            return;
        }

        $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));

        $payload = $docType === CustomerDocType::ChaAuthLetter
            ? [
                'companyId' => $customer->sepio_company_id,
                'dateNow' => now()->valueOf(),
                'documentExtension' => $extension,
                'documentName' => $mapping['documentName'],
            ]
            : [
                'companyId' => $customer->sepio_company_id,
                'IEC' => $customer->iec_number,
                'dateNow' => now()->valueOf(),
                'documentType' => $mapping['documentType'],
                'documentExtension' => $extension,
                'documentName' => $mapping['documentName'],
                'fclFlag' => 1,
                'cfsFlag' => 0,
                'warehouseFlag' => 0,
            ];

        SepioPayloadValidator::uploadKyc($payload);

        $baseName = preg_replace('/[.\s]+/', '_', pathinfo($document->file_name, PATHINFO_FILENAME));
        $safeFileName = $baseName . '.' . $extension;

        $response = $this->client->postFileAs(
            $customer,
            $mapping['endpoint'],
            $payload,
            $fileContents,
            $safeFileName
        );

        if ($response->failed()) {
            $msg = $this->client->parseError($response, 'KYC upload failed.');
            $this->rejectDocument($document, $msg);
            return;
        }

        $fileName = $response->json('data.0.fileName')
            ?? $response->json('data.0.new_fileName')
            ?? null;

        if ($fileName) {
            $document->update(['sepio_file_name' => $fileName]);
        }

        Log::info('Sepio KYC uploaded', [
            'customer_id' => $customer->id,
            'doc_type' => $docType,
            'sepio_file_name' => $fileName,
        ]);
    }

    public function updateCompanyDetails(Customer $customer): void
    {
        if (!$customer->sepio_company_id) return;

        $ports = $customer->ports()->where('port_category', 'port')->get();
        $icds = $customer->ports()->where('port_category', 'icd')->get();

        $billingResponse = $this->client->getAs($customer, '/registrationModule/getbillinglistnew', [
            'companyId' => $customer->sepio_company_id,
        ]);

        $shippingResponse = $this->client->getAs($customer, '/registrationModule/getshippinglist', [
            'companyId' => $customer->sepio_company_id,
        ]);

        if ($billingResponse->failed() || $shippingResponse->failed()) {
            Log::error('Sepio updateCompanyDetails: failed to fetch address lists', [
                'customer_id' => $customer->id,
            ]);
            return;
        }

        $payload = [
            'companyAddressInfo' => [
                'companyName' => $customer->company_name,
                'paymentTerms' => 'credit',
                'creditPeriod' => 30,
                'port' => $this->formatPortList($ports),
                'icd' => $this->formatPortList($icds),
                'unitPriceLock' => 0,
                'distributorId' => config('sepio.distributor_id'),
                'isDistributorChanged' => 0,
                'sendScanEmail' => 0,
                'fclFlag' => 1,
                'cfsFlag' => 0,
                'warehouseFlag' => 0,
                'freightInclusive' => 0,
            ],
            'billingAddressInfo' => $billingResponse->json('data', []),
            'shippingAddressInfo' => $shippingResponse->json('data', []),
        ];

        SepioPayloadValidator::updateCompanyDetails($payload);

        $response = $this->client->postAs($customer, '/registrationModule/updatecompanydetailsnew', $payload);

        if ($response->failed()) {
            Log::error('Sepio updateCompanyDetails failed', [
                'customer_id' => $customer->id,
                'response' => $response->json(),
            ]);
            return;
        }

        Log::info('Sepio company details updated', ['customer_id' => $customer->id]);
    }

    // Private helpers

    /**
     * Pull both the billing and shipping address lists from Sepio,
     * match by pincode + city, and store both IDs on our CustomerLocation.
     * Sepio creates two separate address records even though we sent the same
     * address payload, so we need to pull from each endpoint independently.
     */
    private function pullAndStoreAddressId(Customer $customer, CustomerLocation $location): void
    {
        $updates = [];

        // Billing address ID
        $billingResponse = $this->client->getAs($customer, '/registrationModule/getbillinglistnew', [
            'companyId' => $customer->sepio_company_id,
        ]);

        if ($billingResponse->successful()) {
            Log::debug('Sepio billing address list', $billingResponse->json());

            $matched = collect($billingResponse->json('data', []))->first(
                fn($addr) => strtoupper($addr['zip'] ?? '') === strtoupper($location->pincode ?? '') &&
                    strtoupper($addr['city'] ?? '') === strtoupper($location->city ?? '')
            );

            if ($matched && !empty($matched['addressId'])) {
                $updates['sepio_billing_address_id'] = $matched['addressId'];
            }
        }

        // Shipping address ID
        $shippingResponse = $this->client->getAs($customer, '/registrationModule/getshippinglist', [
            'companyId' => $customer->sepio_company_id,
        ]);

        if ($shippingResponse->successful()) {
            Log::debug('Sepio shipping address list', $shippingResponse->json());

            $matched = collect($shippingResponse->json('data', []))->first(
                fn($addr) => strtoupper($addr['zip'] ?? '') === strtoupper($location->pincode ?? '') &&
                    strtoupper($addr['city'] ?? '') === strtoupper($location->city ?? '')
            );

            if ($matched && !empty($matched['addressId'])) {
                $updates['sepio_shipping_address_id'] = $matched['addressId'];
            }
        }

        if (!empty($updates)) {
            $location->update($updates);
        }
    }

    private function formatPortList($ports, bool $first = false): string
    {
        $strings = $ports->map(fn($p) => "{$p->name} ({$p->code})");
        return $first ? ($strings->first() ?? '') : $strings->implode(',');
    }

    private function docTypeMapping(CustomerDocType $docType): ?array
    {
        return match ($docType) {
            CustomerDocType::GstCrt => ['endpoint' => '/kycData/addkyc', 'documentType' => 'gstCopy', 'documentName' => 'GST'],
            CustomerDocType::IecCert => ['endpoint' => '/kycData/addkyc', 'documentType' => 'iecCopy', 'documentName' => 'IEC'],
            CustomerDocType::PanCard => ['endpoint' => '/kycData/addkyc', 'documentType' => 'panCopy', 'documentName' => 'PAN'],
            CustomerDocType::SelfStuffingCert => ['endpoint' => '/kycData/addselfstuffing', 'documentType' => 'selfCopy', 'documentName' => 'CHEQ'],
            CustomerDocType::CertificateOfRegistration => ['endpoint' => '/kycData/addCORdoc', 'documentType' => 'certificateofRegistrationCompnay', 'documentName' => 'CFSREGISTRATION'],
            CustomerDocType::ChaAuthLetter => ['endpoint' => '/kycData/addchaAuthLetter', 'documentType' => 'chaAuthLetter', 'documentName' => 'CHA'],
            default => null,
        };
    }
}
