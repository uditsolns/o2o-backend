<?php

namespace App\Services\Sepio;

use App\Enums\LocationType;
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
                'distributorId' => config('sepio.distributor_id'),  // was null
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
            'register_from_type' => config('sepio.register_from_type'),  // root level, confirmed name
        ]);

        if ($response->failed() || empty($response->json('company_id'))) {
            throw new \RuntimeException(
                'Sepio registerCompany failed: ' . ($response->json('message') ?? $response->body())
            );
        }

        $sepioCompanyId = (string)$response->json('company_id');

        // Store company_id + encrypted credentials immediately
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

    public function syncLocation(Customer $customer, CustomerLocation $location): void
    {
        // TODO: doubt -> Only shipping address will no work with sepio (LocationType::Shipping)
        $billingAddresses = [];
        $shippingAddresses = [];

        if (in_array($location->location_type, [LocationType::Billing, LocationType::Both])) {
            $billingAddresses[] = [
                'billingCompanyName' => $customer->company_name,
                'address' => $location->address,
                'landmark' => $location->landmark ?? '',
                'zipcode' => $location->pincode,
                'city' => $location->city,
                'state' => strtoupper($location->state),
                'gstno' => $location->gst_number ?? $customer->gst_number ?? '',
            ];
        }

        if (in_array($location->location_type, [LocationType::Shipping, LocationType::Both])) {
            $shippingAddresses[] = [
                'address' => $location->address,
                'landmark' => $location->landmark ?? '',
                'city' => $location->city,
                'state' => strtoupper($location->state),
                'zipcode' => $location->pincode,
            ];
        }

        if (empty($billingAddresses) && empty($shippingAddresses)) return;

        $response = $this->client->postAs($customer, '/registrationModule/updateaddress', [
            'createdBy' => $customer->primary_contact_email ?? $customer->email,
            'companyId' => $customer->sepio_company_id,
            'billingAddressInfo' => ['billAddresses' => $billingAddresses],
            'shippingAddressInfo' => ['addresses' => $shippingAddresses],
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

        Log::debug('Address updated', $response->json());

        $this->pullAndStoreAddressId($customer, $location, $location->location_type);
    }

    // Upload all existing documents

    public function uploadAllDocuments(Customer $customer): void
    {
        $documents = $customer->documents()->get();

        foreach ($documents as $document) {
            $this->uploadDocument($customer, $document);
        }
    }

    public function uploadDocument(Customer $customer, CustomerDocument $document): void
    {
        $docType = is_string($document->doc_type)
            ? $document->doc_type
            : $document->doc_type->value;

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

        // Build payload WITHOUT 'file' key — file goes as binary attachment
        $payload = $docType === 'cha_auth_letter'
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

    private function pullAndStoreAddressId(
        Customer         $customer,
        CustomerLocation $location,
        LocationType     $locationType
    ): void
    {
        $endpoint = in_array($locationType, [LocationType::Billing, LocationType::Both])
            ? '/registrationModule/getbillinglistnew'
            : '/registrationModule/getshippinglist';

        $response = $this->client->getAs($customer, $endpoint, [
            'companyId' => $customer->sepio_company_id,
        ]);

        if ($response->failed()) return;

        Log::debug("Address List Response: ", $response->json());

        $matched = collect($response->json('data', []))->first(fn($addr) => strtoupper($addr['zip'] ?? '') === strtoupper($location->pincode ?? '') &&
            strtoupper($addr['city'] ?? '') === strtoupper($location->city ?? '')
        );

        if ($matched && !empty($matched['addressId'])) {
            $location->update(['sepio_address_id' => $matched['addressId']]);
        }
    }

    private function formatPortList($ports, bool $first = false): string
    {
        $strings = $ports->map(fn($p) => "{$p->name} ({$p->code})");
        return $first ? ($strings->first() ?? '') : $strings->implode(',');
    }

    private function docTypeMapping(string $docType): ?array
    {
        return match ($docType) {
            'gst_cert' => ['endpoint' => '/kycData/addkyc', 'documentType' => 'gstCopy', 'documentName' => 'GST'],
            'iec_cert' => ['endpoint' => '/kycData/addkyc', 'documentType' => 'iecCopy', 'documentName' => 'IEC'],
            'pan_card' => ['endpoint' => '/kycData/addkyc', 'documentType' => 'panCopy', 'documentName' => 'PAN'],
            'self_stuffing_cert' => ['endpoint' => '/kycData/addselfstuffing', 'documentType' => 'selfCopy', 'documentName' => 'CHEQ'],
            'certificate_of_registration' => ['endpoint' => '/kycData/addCORdoc', 'documentType' => 'certificateofRegistrationCompnay', 'documentName' => 'CFSREGISTRATION'],
            'cha_auth_letter' => ['endpoint' => '/kycData/addchaAuthLetter', 'documentType' => 'chaAuthLetter', 'documentName' => 'CHA'],
            default => null,
        };
    }
}
