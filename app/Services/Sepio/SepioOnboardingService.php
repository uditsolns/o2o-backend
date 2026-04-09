<?php

namespace App\Services\Sepio;

use App\Enums\CustomerDocType;
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

        $plainPassword = Str::random(16);

        $response = $this->client->post('/registrationModule/registercompany', [
            'companydetailsInfo' => [
                'companyName' => $customer->company_name,
                'IEC' => $customer->iec_number,
                'sealRequest' => '1000',
                'port' => $this->formatPortList($ports),
                'icd' => $this->formatPortList($icds),
                'cfsLocation' => $this->formatPortList($cfsItems, first: true),
                'chaUser' => $customer->cha_number ?? '',
                'chaId' => $customer->cha_number ?? '',
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
        ]);

        if ($response->failed() || empty($response->json('company_id'))) {
            throw new \RuntimeException(
                'Sepio registerCompany failed: ' . ($response->json('message') ?? $response->body())
            );
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
        $locations = $customer->locations()->where('is_active', true)->get();

        foreach ($locations as $location) {
            $this->syncLocation($customer, $location);
        }
    }

    /**
     * Push a location to Sepio as BOTH billing and shipping address.
     */
    public function syncLocation(Customer $customer, CustomerLocation $location): void
    {
        $response = $this->client->postAs($customer, '/registrationModule/updateaddress', [
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
            'cfsFlag' => 1,
            'warehouseFlag' => 0,
        ]);

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
            $this->uploadDocument($customer, $document);
        }
    }

    public function uploadDocument(Customer $customer, CustomerDocument $document): void
    {
        $docType = $document->doc_type;

        $mapping = $this->docTypeMapping($docType);
        if (!$mapping) return;

        $fileContents = Storage::get($document->url);

        if (!$fileContents) {
            Log::error('Sepio KYC upload — file missing in storage', [
                'customer_id' => $customer->id,
                'doc_id' => $document->id,
            ]);
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
                'cfsFlag' => 1,
                'warehouseFlag' => 0,
            ];

        $response = $this->client->postFileAs(
            $customer,
            $mapping['endpoint'],
            $payload,
            $fileContents,
            $document->file_name
        );

        if ($response->failed()) {
            Log::error('Sepio KYC upload failed', [
                'customer_id' => $customer->id,
                'doc_id' => $document->id,
                'doc_type' => $docType,
                'response' => $response->json(),
            ]);
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
