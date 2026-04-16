<?php

namespace App\Services\Sepio;

use App\Enums\SealStatus;
use App\Exceptions\SepioException;
use App\Models\Customer;
use App\Models\Seal;
use App\Models\Trip;
use Illuminate\Support\Facades\Log;

readonly class SepioSealService
{
    public function __construct(private SepioClient $client)
    {
    }

    /**
     * Check if a seal is available for installation before assigning to a trip.
     * Returns availability data or throws on hard failures.
     */
    public function checkSealAvailability(Customer $customer, Seal $seal): array
    {
        // Sepio expects sealString (prefix) and sealNo (numeric part) separately
        preg_match('/^([A-Z]+)(\d+)$/', $seal->seal_number, $matches);

        if (!$matches) {
            return [
                'available' => false,
                'message' => 'Invalid seal number format.',
            ];
        }

        $sealString = $matches[1]; // e.g. "SPPL"
        $sealNo = $matches[2]; // e.g. "10009067"

        $response = $this->client->postAs($customer, '/installationUser/singleinstallsealcheck', [
            'sealString' => $sealString,
            'sealNo' => $sealNo,
            'companyId' => $customer->sepio_company_id,
        ]);

        if ($response->failed()) {
            Log::warning('Sepio seal check failed', [
                'seal_id' => $seal->id,
                'seal_number' => $seal->seal_number,
                'response' => $response->json(),
            ]);

            return [
                'available' => false,
                'message' => $response->json('message') ?? 'Sepio check failed.',
            ];
        }

        $data = $response->json('data', []);

        return [
            'available' => $data['sealAvailable'] ?? false,
            'message' => $data['message'] ?? 'Seal available.',
            'order_id' => $data['orderId'] ?? null,
            'ports' => $data['PORT'] ?? [],
            'icds' => $data['ICD'] ?? [],
            'iec' => $data['IEC'] ?? null,
        ];
    }

    public function installSeal(Customer $customer, Trip $trip): bool
    {
        $seal = $trip->seal;

        if (!$seal) return false;

        preg_match('/^([A-Z]+)(\d+)$/', $seal->seal_number, $matches);
        if (!$matches) return false;

        $sepioOrderId = $seal->order?->sepio_order_id;
        if (!$sepioOrderId) return false;

        $destinationPort = $trip->destination_port_code
            ? "{$trip->destination_port_name} ({$trip->destination_port_code})"
            : null;

        $connectingPort = $trip->origin_port_code
            ? "{$trip->origin_port_name} ({$trip->origin_port_code})"
            : null;

        $sealingDate = $seal->created_at->format('Y-m-d')
            ?? $trip->dispatch_date?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $sealingTime = $trip->trip_start_time?->format('H:i:s')
            ?? now()->format('H:i:s');

        $response = $this->client->postAs($customer, '/installationUser/installseal', [
            'sealString' => $matches[1],
            'sealNo' => $matches[2],
            'companyId' => $customer->sepio_company_id,
            'createdBy' => $customer->primary_contact_email ?? $customer->email,
            'shippingBillNo' => [$trip->eway_bill_number ?? ''],
            'shippingBillDate' => [
                $trip->invoice_date
                    ? $trip->invoice_date->format('d-m-Y')
                    : now()->format('d-m-Y'),
            ],
            'sealingDate' => $sealingDate,
            'sealingTime' => $sealingTime,
            'destinationStation' => $destinationPort,
            'connectingPort' => $connectingPort,
            'containerNo' => $trip->container_number ?? '',
            'truckNo' => $trip->vehicle_number ?? '',
            'orderId' => $sepioOrderId,
            'sealDraftId' => 'default',
            'ebnNo' => [[[$trip->eway_bill_number ?? ''], [], [0]]],
        ]);

        if ($response->failed()) {
            $json = $response->json() ?? [];
            $msg = SepioException::extractMessage($json) ?: 'Seal installation failed.';
            Log::error('Sepio installSeal failed', [
                'trip_id' => $trip->id,
                'seal_number' => $seal->seal_number,
                'error' => $msg,
            ]);
            // Expose the Sepio error to the caller so the trip update can surface it
            throw new SepioException($msg, $json);
        }

        $seal->update(['status' => SealStatus::InTransit]);

        Log::info('Sepio seal installed', [
            'trip_id' => $trip->id,
            'seal_number' => $seal->seal_number,
        ]);

        return true;
    }
}
