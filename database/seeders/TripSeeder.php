<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\SealStatus;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\Port;
use App\Models\Seal;
use App\Models\Trip;
use App\Models\TripDocument;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::where('onboarding_status', CustomerOnboardingStatus::Completed->value)->get();
        $seaPorts = Port::where('port_category', 'port')->where('is_active', true)->get();
        $total = 0;

        foreach ($customers as $customer) {
            $user = User::where('customer_id', $customer->id)->firstOrFail();

            $locations = CustomerLocation::where('customer_id', $customer->id)->get();
            $dispatchLoc = $locations->first(fn($l) => !empty($l->sepio_shipping_address_id));
            $deliveryLoc = $locations->first(fn($l) => !empty($l->sepio_billing_address_id));
            $originPort = $seaPorts->shuffle()->first();
            $destPort = $seaPorts->shuffle()->first();

            if (!$dispatchLoc || !$deliveryLoc) continue;

            $availableSeals = Seal::where('customer_id', $customer->id)
                ->where('status', SealStatus::InInventory->value)
                ->whereNull('trip_id')
                ->get();

            $sealIterator = $availableSeals->getIterator();

            foreach ($this->tripDefinitions($customer, $user, $dispatchLoc, $deliveryLoc, $originPort, $destPort) as $def) {
                if (Trip::where('trip_ref', $def['trip_ref'])->exists()) {
                    $total++;
                    continue;
                }

                $needsSeal = $def['needs_seal'];
                unset($def['needs_seal']);

                $seal = null;
                if ($needsSeal && $sealIterator->valid()) {
                    $seal = $sealIterator->current();
                    $sealIterator->next();
                }

                $trip = Trip::create(array_merge($def, ['seal_id' => $seal?->id]));

                if ($seal) {
                    $assignStatus = match ($trip->status) {
                        TripStatus::Completed => SealStatus::Used,
                        TripStatus::InTransit, TripStatus::AtPort,
                        TripStatus::OnVessel, TripStatus::InTransshipment,
                        TripStatus::VesselArrived, TripStatus::OutForDelivery,
                        TripStatus::Delivered => SealStatus::InTransit,
                        default => SealStatus::Assigned,
                    };
                    $seal->update(['status' => $assignStatus->value, 'trip_id' => $trip->id]);
                }

                $this->seedEvents($trip, $user);
                $this->seedDocuments($trip, $user);

                $total++;
            }
        }

        $this->command->info("  TripSeeder: {$total} trips seeded.");
    }

    private function tripDefinitions(
        Customer         $customer, User $user,
        CustomerLocation $dispatchLoc, CustomerLocation $deliveryLoc,
        ?Port            $originPort, ?Port $destPort
    ): array
    {
        $cid = $customer->id;
        $base = [
            'customer_id' => $cid,
            'created_by_id' => $user->id,
            // Dispatch snapshot
            'dispatch_location_name' => $dispatchLoc->name,
            'dispatch_address' => $dispatchLoc->address,
            'dispatch_city' => $dispatchLoc->city,
            'dispatch_state' => $dispatchLoc->state,
            'dispatch_pincode' => $dispatchLoc->pincode,
            'dispatch_country' => $dispatchLoc->country ?? 'India',
            'dispatch_contact_person' => $dispatchLoc->contact_person,
            'dispatch_contact_number' => $dispatchLoc->contact_number,
            'dispatch_contact_email' => 'dispatch@example.com',
            'dispatch_lat' => $dispatchLoc->lat,
            'dispatch_lng' => $dispatchLoc->lng,
            // Delivery snapshot
            'delivery_location_name' => $deliveryLoc->name,
            'delivery_address' => $deliveryLoc->address,
            'delivery_city' => $deliveryLoc->city,
            'delivery_state' => $deliveryLoc->state,
            'delivery_pincode' => $deliveryLoc->pincode,
            'delivery_country' => $deliveryLoc->country ?? 'India',
            'delivery_contact_person' => $deliveryLoc->contact_person,
            'delivery_contact_number' => $deliveryLoc->contact_number,
            'delivery_contact_email' => 'delivery@example.com',
            'delivery_lat' => $deliveryLoc->lat,
            'delivery_lng' => $deliveryLoc->lng,
            // Port snapshots
            'origin_port_name' => $originPort?->name,
            'origin_port_code' => $originPort?->code,
            'origin_port_category' => $originPort?->port_category->value,
            'destination_port_name' => $destPort?->name,
            'destination_port_code' => $destPort?->code,
            'destination_port_category' => $destPort?->port_category->value,
            // Common cargo
            'cargo_type' => 'Machinery Parts',
            'cargo_description' => 'Industrial CNC spindle assemblies and hydraulic actuators.',
            'hs_code' => '8479.89',
            'gross_weight' => 14500.00,
            'net_weight' => 14200.00,
            'weight_unit' => 'kg',
            'quantity' => 24,
            'quantity_unit' => 'packages',
            'declared_cargo_value' => 850000.00,
            // Vehicle
            'vehicle_type' => 'container_carrier',
            'driver_name' => 'Ramesh Kumar',
            'driver_phone' => '9700123456',
            'driver_license' => 'MH01-2019-0012345',
            'epod_status' => 'pending',
        ];

        return [
            // 1. Draft
            array_merge($base, [
                'trip_ref' => "TR{$cid}T001",
                'status' => TripStatus::Draft,
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Sea,
                'tracking_token' => Str::random(32),
                'vehicle_number' => 'MH04AB' . rand(1000, 9999),
                'container_number' => 'MSCU' . rand(1000000, 9999999),
                'container_type' => '40HC',
                'carrier_scac' => 'MSCU',
                'invoice_number' => 'INV-' . $cid . '-001',
                'invoice_date' => now()->subDays(2)->toDateString(),
                'eway_bill_number' => null,
                'eway_bill_validity_date' => null,
                'dispatch_date' => now()->addDays(3)->toDateString(),
                'expected_delivery_date' => now()->addDays(30)->toDateString(),
                'needs_seal' => false,
            ]),

            // 2. In Transit — road leg
            array_merge($base, [
                'trip_ref' => "TR{$cid}T002",
                'status' => TripStatus::InTransit,
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Multimodal,
                'tracking_token' => Str::random(32),
                'vehicle_number' => 'DL01CD' . rand(1000, 9999),
                'container_number' => 'HLCU' . rand(1000000, 9999999),
                'container_type' => '20GP',
                'carrier_scac' => 'HLCU',
                'invoice_number' => 'INV-' . $cid . '-002',
                'invoice_date' => now()->subDays(10)->toDateString(),
                'eway_bill_number' => fake()->numerify('##############'),
                'eway_bill_validity_date' => now()->addDays(12)->toDateString(),
                'dispatch_date' => now()->subDays(8)->toDateString(),
                'trip_start_time' => now()->subDays(8),
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'needs_seal' => true,
            ]),

            // 3. At Port
            array_merge($base, [
                'trip_ref' => "TR{$cid}T003",
                'status' => TripStatus::AtPort,
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Multimodal,
                'tracking_token' => Str::random(32),
                'vehicle_number' => 'GJ05EF' . rand(1000, 9999),
                'container_number' => 'EVRU' . rand(1000000, 9999999),
                'container_type' => '40GP',
                'carrier_scac' => 'EGLV',
                'invoice_number' => 'INV-' . $cid . '-003',
                'invoice_date' => now()->subDays(18)->toDateString(),
                'eway_bill_number' => fake()->numerify('##############'),
                'eway_bill_validity_date' => now()->addDays(4)->toDateString(),
                'dispatch_date' => now()->subDays(15)->toDateString(),
                'trip_start_time' => now()->subDays(15),
                'expected_delivery_date' => now()->addDays(10)->toDateString(),
                'needs_seal' => true,
            ]),

            // 4. On Vessel
            array_merge($base, [
                'trip_ref' => "TR{$cid}T004",
                'status' => TripStatus::OnVessel,
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Sea,
                'tracking_token' => Str::random(32),
                'vehicle_number' => null,
                'container_number' => 'CMAU' . rand(1000000, 9999999),
                'container_type' => '40HC',
                'carrier_scac' => 'CMDU',
                'invoice_number' => 'INV-' . $cid . '-004',
                'invoice_date' => now()->subDays(25)->toDateString(),
                'eway_bill_number' => null,
                'eway_bill_validity_date' => null,
                'vessel_name' => 'MSC GÜLSÜN',
                'vessel_imo_number' => 'IMO9811000',
                'voyage_number' => 'AE1-123W',
                'bill_of_lading' => 'MSCUBO' . rand(100000, 999999),
                'carrier_scac' => 'MSCU',
                'eta' => now()->addDays(20),
                'etd' => now()->subDays(3),
                'dispatch_date' => now()->subDays(22)->toDateString(),
                'trip_start_time' => now()->subDays(22),
                'expected_delivery_date' => now()->addDays(25)->toDateString(),
                'needs_seal' => true,
            ]),

            // 5. In Transshipment
            array_merge($base, [
                'trip_ref' => "TR{$cid}T005",
                'status' => TripStatus::InTransshipment,
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Sea,
                'tracking_token' => Str::random(32),
                'vehicle_number' => null,
                'container_number' => 'COSU' . rand(1000000, 9999999),
                'container_type' => '20GP',
                'carrier_scac' => 'COSU',
                'invoice_number' => 'INV-' . $cid . '-005',
                'invoice_date' => now()->subDays(30)->toDateString(),
                'eway_bill_number' => null,
                'eway_bill_validity_date' => null,
                'vessel_name' => 'CMA CGM Antoine de Saint Exupery',
                'vessel_imo_number' => 'IMO9776171',
                'voyage_number' => 'FAL1-456W',
                'bill_of_lading' => 'COSU' . rand(100000, 999999),
                'eta' => now()->addDays(12),
                'etd' => now()->subDays(10),
                'dispatch_date' => now()->subDays(28)->toDateString(),
                'trip_start_time' => now()->subDays(28),
                'expected_delivery_date' => now()->addDays(15)->toDateString(),
                'needs_seal' => true,
            ]),

            // 6. Vessel Arrived
            array_merge($base, [
                'trip_ref' => "TR{$cid}T006",
                'status' => TripStatus::VesselArrived,
                'trip_type' => TripType::Import,
                'transport_mode' => TripTransportationMode::Sea,
                'tracking_token' => Str::random(32),
                'vehicle_number' => null,
                'container_number' => 'APLU' . rand(1000000, 9999999),
                'container_type' => '20GP',
                'carrier_scac' => 'APLU',
                'invoice_number' => 'INV-' . $cid . '-006',
                'invoice_date' => now()->subDays(40)->toDateString(),
                'eway_bill_number' => null,
                'eway_bill_validity_date' => null,
                'vessel_name' => 'Ever Given',
                'vessel_imo_number' => 'IMO9811001',
                'voyage_number' => 'AW2-456E',
                'bill_of_lading' => 'EGLV' . rand(100000, 999999),
                'eta' => now()->subDays(2),
                'etd' => now()->subDays(30),
                'dispatch_date' => now()->subDays(35)->toDateString(),
                'trip_start_time' => now()->subDays(35),
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'needs_seal' => true,
            ]),

            // 7. Out For Delivery
            array_merge($base, [
                'trip_ref' => "TR{$cid}T007",
                'status' => TripStatus::OutForDelivery,
                'trip_type' => TripType::Import,
                'transport_mode' => TripTransportationMode::Road,
                'tracking_token' => Str::random(32),
                'vehicle_number' => 'MH12KL' . rand(1000, 9999),
                'container_number' => null,
                'container_type' => null,
                'carrier_scac' => null,
                'invoice_number' => 'INV-' . $cid . '-007',
                'invoice_date' => now()->subDays(12)->toDateString(),
                'eway_bill_number' => fake()->numerify('##############'),
                'eway_bill_validity_date' => now()->addDays(3)->toDateString(),
                'dispatch_date' => now()->subDays(10)->toDateString(),
                'trip_start_time' => now()->subDays(10),
                'expected_delivery_date' => now()->addDay()->toDateString(),
                'needs_seal' => true,
            ]),

            // 8. Delivered
            array_merge($base, [
                'trip_ref' => "TR{$cid}T008",
                'status' => TripStatus::Delivered,
                'trip_type' => TripType::Domestic,
                'transport_mode' => TripTransportationMode::Road,
                'tracking_token' => Str::random(32),
                'vehicle_number' => 'TN09GH' . rand(1000, 9999),
                'container_number' => null,
                'container_type' => null,
                'carrier_scac' => null,
                'invoice_number' => 'INV-' . $cid . '-008',
                'invoice_date' => now()->subDays(15)->toDateString(),
                'eway_bill_number' => fake()->numerify('##############'),
                'eway_bill_validity_date' => now()->subDay()->toDateString(),
                'dispatch_date' => now()->subDays(12)->toDateString(),
                'trip_start_time' => now()->subDays(12),
                'actual_delivery_date' => now()->subDays(2)->toDateString(),
                'expected_delivery_date' => now()->subDays(2)->toDateString(),
                'needs_seal' => true,
            ]),

            // 9. Completed
            array_merge($base, [
                'trip_ref' => "TR{$cid}T009",
                'status' => TripStatus::Completed,
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Multimodal,
                'tracking_token' => Str::random(32),
                'vehicle_number' => 'KA03IJ' . rand(1000, 9999),
                'container_number' => 'OOLU' . rand(1000000, 9999999),
                'container_type' => '40HC',
                'carrier_scac' => 'OOLU',
                'invoice_number' => 'INV-' . $cid . '-009',
                'invoice_date' => now()->subDays(45)->toDateString(),
                'eway_bill_number' => fake()->numerify('##############'),
                'eway_bill_validity_date' => now()->subDays(20)->toDateString(),
                'vessel_name' => 'CSCL Globe',
                'vessel_imo_number' => 'IMO9695154',
                'voyage_number' => 'AE7-789W',
                'bill_of_lading' => 'OOLU' . rand(100000, 999999),
                'etd' => now()->subDays(40),
                'eta' => now()->subDays(20),
                'dispatch_date' => now()->subDays(40)->toDateString(),
                'trip_start_time' => now()->subDays(40),
                'actual_delivery_date' => now()->subDays(20)->toDateString(),
                'trip_end_time' => now()->subDays(20),
                'expected_delivery_date' => now()->subDays(20)->toDateString(),
                'epod_status' => 'completed',
                'epod_confirmed_at' => now()->subDays(19),
                'epod_confirmed_by_id' => $user->id,
                'epod_confirmation_notes' => 'All items received in good condition.',
                'needs_seal' => true,
            ]),
        ];
    }

    private function seedEvents(Trip $trip, User $user): void
    {
        $statusFlow = [
            TripStatus::Draft,
            TripStatus::InTransit,
            TripStatus::AtPort,
            TripStatus::OnVessel,
            TripStatus::InTransshipment,
            TripStatus::VesselArrived,
            TripStatus::OutForDelivery,
            TripStatus::Delivered,
            TripStatus::Completed,
        ];

        $currentIndex = array_search($trip->status, $statusFlow);
        if ($currentIndex === false) $currentIndex = 0;

        for ($i = 0; $i <= $currentIndex; $i++) {
            TripEvent::insert([
                'customer_id' => $trip->customer_id,
                'trip_id' => $trip->id,
                'event_type' => $i === 0 ? 'trip_created' : 'status_changed',
                'previous_status' => $i > 0 ? $statusFlow[$i - 1]->value : null,
                'new_status' => $statusFlow[$i]->value,
                'event_data' => json_encode(['trip_ref' => $trip->trip_ref]),
                'actor_type' => 'user',
                'actor_id' => $user->id,
                'created_at' => now()->subDays(45 - ($i * 4)),
            ]);
        }

        if ($trip->vessel_name && in_array($trip->status, [
                TripStatus::OnVessel, TripStatus::InTransshipment, TripStatus::VesselArrived,
                TripStatus::OutForDelivery, TripStatus::Delivered, TripStatus::Completed,
            ])) {
            TripEvent::insert([
                'customer_id' => $trip->customer_id,
                'trip_id' => $trip->id,
                'event_type' => 'vessel_info_added',
                'event_data' => json_encode([
                    'vessel_name' => $trip->vessel_name,
                    'imo' => $trip->vessel_imo_number,
                ]),
                'actor_type' => 'user',
                'actor_id' => $user->id,
                'created_at' => now()->subDays(20),
            ]);
        }
    }

    private function seedDocuments(Trip $trip, User $user): void
    {
        $docs = [
            ['doc_type' => 'e_way_bill', 'file_name' => 'EWayBill.pdf'],
            ['doc_type' => 'e_invoice', 'file_name' => 'EInvoice.pdf'],
        ];

        if (in_array($trip->status, [
            TripStatus::Delivered, TripStatus::Completed,
        ])) {
            $docs[] = ['doc_type' => 'e_pod', 'file_name' => 'EPOD.pdf'];
        }

        foreach ($docs as $doc) {
            if (TripDocument::where('trip_id', $trip->id)->where('doc_type', $doc['doc_type'])->exists()) {
                continue;
            }
            TripDocument::insert([
                'trip_id' => $trip->id,
                'customer_id' => $trip->customer_id,
                'uploaded_by_id' => $user->id,
                'doc_type' => $doc['doc_type'],
                'file_name' => $doc['file_name'],
                'url' => "trips/{$trip->id}/documents/{$doc['file_name']}",
                'created_at' => now()->subDays(rand(1, 5)),
            ]);
        }
    }
}
