<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\CustomerPort;
use App\Models\CustomerRoute;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerRouteSeeder extends Seeder
{
    public function run(): array
    {
        $customers = Customer::whereIn('onboarding_status', [
            CustomerOnboardingStatus::IlApproved->value,
            CustomerOnboardingStatus::Completed->value,
        ])->get();

        $result = [];
        $total = 0;

        foreach ($customers as $customer) {
            $user = User::where('customer_id', $customer->id)->firstOrFail();
            $locations = CustomerLocation::where('customer_id', $customer->id)->get();
            $ports = CustomerPort::where('customer_id', $customer->id)->get();

            $defs = $this->definitionsFor($customer, $locations, $ports, $user);
            $customerRoutes = [];

            foreach ($defs as $def) {
                $route = CustomerRoute::firstOrCreate(
                    ['customer_id' => $customer->id, 'name' => $def['name']],
                    array_merge($def, ['customer_id' => $customer->id])
                );
                $customerRoutes[] = $route;
                $total++;
            }

            $result[$customer->id] = $customerRoutes;
        }

        $this->command->info("  CustomerRouteSeeder: {$total} routes seeded.");

        return $result;
    }

    // ── Per-customer route definitions ────────────────────────────────────────

    private function definitionsFor(Customer $customer, $locations, $ports, User $user): array
    {
        return match ($customer->email) {
            'kiran.verma@vermalogistics.test' => $this->vermaRoutes($locations, $ports, $user),
            'meena.iyer@iyerimpex.test' => $this->iyerRoutes($locations, $ports, $user),
            default => $this->genericRoutes($customer, $locations, $ports, $user),
        };
    }

    private function vermaRoutes($locations, $ports, User $user): array
    {
        $ho = $locations->firstWhere('name', 'Verma Logistics — Mayapuri HO');
        $noida = $locations->firstWhere('name', 'Verma Logistics — NSEZ Noida Warehouse');
        $gurgaon = $locations->firstWhere('name', 'Verma Logistics — Gurgaon Branch');
        $jnpt = $ports->firstWhere('code', 'INNSA');
        $mundra = $ports->firstWhere('code', 'INMUN');

        return [
            // 1. Export Multimodal: Delhi factory → JNPT → (sea to Dubai/UAE)
            [
                'name' => 'Export Multimodal: Delhi → JNPT → Jebel Ali',
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Multimodal,
                'dispatch_location_name' => $ho?->name,
                'dispatch_address' => $ho?->address,
                'dispatch_city' => 'New Delhi',
                'dispatch_state' => 'Delhi',
                'dispatch_pincode' => '110064',
                'dispatch_country' => 'India',
                'dispatch_lat' => 28.6358,
                'dispatch_lng' => 77.1022,
                'delivery_location_name' => 'Jebel Ali Free Zone Warehouse',
                'delivery_address' => 'JAFZA North, Jebel Ali',
                'delivery_city' => 'Dubai',
                'delivery_state' => 'Dubai',
                'delivery_pincode' => '17000',
                'delivery_country' => 'UAE',
                'delivery_lat' => 24.9857,
                'delivery_lng' => 55.0272,
                'origin_port_name' => 'Jawaharlal Nehru Port (JNPT)',
                'origin_port_code' => 'INNSA',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Jebel Ali Port',
                'destination_port_code' => 'AEJEA',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 2. Export Sea: JNPT → Rotterdam
            [
                'name' => 'Export Sea: JNPT → Rotterdam',
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Sea,
                'dispatch_location_name' => 'Jawaharlal Nehru Port (JNPT)',
                'dispatch_address' => 'JNPT, Sheva, Navi Mumbai',
                'dispatch_city' => 'Navi Mumbai',
                'dispatch_state' => 'Maharashtra',
                'dispatch_pincode' => '400707',
                'dispatch_country' => 'India',
                'dispatch_lat' => 18.9488,
                'dispatch_lng' => 72.9511,
                'delivery_location_name' => 'Port of Rotterdam, Netherlands',
                'delivery_address' => 'Maasvlakte, Rotterdam',
                'delivery_city' => 'Rotterdam',
                'delivery_state' => 'South Holland',
                'delivery_pincode' => '3199',
                'delivery_country' => 'Netherlands',
                'delivery_lat' => 51.9244,
                'delivery_lng' => 4.4777,
                'origin_port_name' => 'Jawaharlal Nehru Port (JNPT)',
                'origin_port_code' => 'INNSA',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Port of Rotterdam',
                'destination_port_code' => 'NLRTM',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 3. Import Multimodal: Jebel Ali → JNPT → Delhi warehouse
            [
                'name' => 'Import Multimodal: Jebel Ali → JNPT → Delhi',
                'trip_type' => TripType::Import,
                'transport_mode' => TripTransportationMode::Multimodal,
                'dispatch_location_name' => 'Jebel Ali Port',
                'dispatch_address' => 'JAFZA, Jebel Ali',
                'dispatch_city' => 'Dubai',
                'dispatch_state' => 'Dubai',
                'dispatch_pincode' => '17000',
                'dispatch_country' => 'UAE',
                'dispatch_lat' => 24.9857,
                'dispatch_lng' => 55.0272,
                'delivery_location_name' => $noida?->name,
                'delivery_address' => $noida?->address,
                'delivery_city' => 'Noida',
                'delivery_state' => 'Uttar Pradesh',
                'delivery_pincode' => '201305',
                'delivery_country' => 'India',
                'delivery_lat' => 28.5706,
                'delivery_lng' => 77.3522,
                'origin_port_name' => 'Jebel Ali Port',
                'origin_port_code' => 'AEJEA',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Jawaharlal Nehru Port (JNPT)',
                'destination_port_code' => 'INNSA',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 4. Import Sea: Rotterdam → JNPT
            [
                'name' => 'Import Sea: Rotterdam → JNPT',
                'trip_type' => TripType::Import,
                'transport_mode' => TripTransportationMode::Sea,
                'dispatch_location_name' => 'Port of Rotterdam',
                'dispatch_address' => 'Maasvlakte, Rotterdam',
                'dispatch_city' => 'Rotterdam',
                'dispatch_state' => 'South Holland',
                'dispatch_pincode' => '3199',
                'dispatch_country' => 'Netherlands',
                'dispatch_lat' => 51.9244,
                'dispatch_lng' => 4.4777,
                'delivery_location_name' => 'Jawaharlal Nehru Port (JNPT)',
                'delivery_address' => 'JNPT, Sheva, Navi Mumbai',
                'delivery_city' => 'Navi Mumbai',
                'delivery_state' => 'Maharashtra',
                'delivery_pincode' => '400707',
                'delivery_country' => 'India',
                'delivery_lat' => 18.9488,
                'delivery_lng' => 72.9511,
                'origin_port_name' => 'Port of Rotterdam',
                'origin_port_code' => 'NLRTM',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Jawaharlal Nehru Port (JNPT)',
                'destination_port_code' => 'INNSA',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 5. Domestic Road: Delhi → Mumbai
            [
                'name' => 'Domestic Road: Delhi → Mumbai',
                'trip_type' => TripType::Domestic,
                'transport_mode' => TripTransportationMode::Road,
                'dispatch_location_name' => $ho?->name,
                'dispatch_address' => $ho?->address,
                'dispatch_city' => 'New Delhi',
                'dispatch_state' => 'Delhi',
                'dispatch_pincode' => '110064',
                'dispatch_country' => 'India',
                'dispatch_lat' => 28.6358,
                'dispatch_lng' => 77.1022,
                'delivery_location_name' => 'Bhiwandi Logistics Hub',
                'delivery_address' => 'Plot 22, Bhiwandi Warehousing Complex',
                'delivery_city' => 'Mumbai',
                'delivery_state' => 'Maharashtra',
                'delivery_pincode' => '421302',
                'delivery_country' => 'India',
                'delivery_lat' => 19.2963,
                'delivery_lng' => 73.0517,
                'origin_port_name' => null,
                'origin_port_code' => null,
                'origin_port_category' => null,
                'destination_port_name' => null,
                'destination_port_code' => null,
                'destination_port_category' => null,
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 6. Export Multimodal via Mundra: Noida → Mundra → Singapore
            [
                'name' => 'Export Multimodal: Noida → Mundra → Singapore',
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Multimodal,
                'dispatch_location_name' => $noida?->name,
                'dispatch_address' => $noida?->address,
                'dispatch_city' => 'Noida',
                'dispatch_state' => 'Uttar Pradesh',
                'dispatch_pincode' => '201305',
                'dispatch_country' => 'India',
                'dispatch_lat' => 28.5706,
                'dispatch_lng' => 77.3522,
                'delivery_location_name' => 'Singapore Port (PSA)',
                'delivery_address' => 'Tanjong Pagar Terminal, Singapore',
                'delivery_city' => 'Singapore',
                'delivery_state' => 'Singapore',
                'delivery_pincode' => '099253',
                'delivery_country' => 'Singapore',
                'delivery_lat' => 1.2566,
                'delivery_lng' => 103.8198,
                'origin_port_name' => 'Mundra Port (APSEZ)',
                'origin_port_code' => 'INMUN',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Port of Singapore (PSA)',
                'destination_port_code' => 'SGSIN',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
        ];
    }

    private function iyerRoutes($locations, $ports, User $user): array
    {
        $factory = $locations->firstWhere('name', 'Iyer Impex — Ambattur Factory');
        $blr = $locations->firstWhere('name', 'Iyer Impex — Bengaluru Warehouse');
        $portOfc = $locations->firstWhere('name', 'Iyer Impex — Chennai Port Office');
        $cma = $ports->firstWhere('code', 'INMAA');
        $jnpt = $ports->firstWhere('code', 'INNSA');

        return [
            // 1. Export Sea: Chennai → Hamburg
            [
                'name' => 'Export Sea: Chennai → Hamburg',
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Sea,
                'dispatch_location_name' => 'Chennai Port (Kamarajar Port)',
                'dispatch_address' => 'Rajaji Salai, George Town',
                'dispatch_city' => 'Chennai',
                'dispatch_state' => 'Tamil Nadu',
                'dispatch_pincode' => '600001',
                'dispatch_country' => 'India',
                'dispatch_lat' => 13.0836,
                'dispatch_lng' => 80.2969,
                'delivery_location_name' => 'Port of Hamburg',
                'delivery_address' => 'Am Kaiserkai, Hamburg',
                'delivery_city' => 'Hamburg',
                'delivery_state' => 'Hamburg',
                'delivery_pincode' => '20457',
                'delivery_country' => 'Germany',
                'delivery_lat' => 53.5439,
                'delivery_lng' => 9.9569,
                'origin_port_name' => 'Chennai Port (Kamarajar Port)',
                'origin_port_code' => 'INMAA',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Port of Hamburg',
                'destination_port_code' => 'DEHAM',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 2. Import Multimodal: Shanghai → JNPT → Chennai Factory
            [
                'name' => 'Import Multimodal: Shanghai → JNPT → Chennai',
                'trip_type' => TripType::Import,
                'transport_mode' => TripTransportationMode::Multimodal,
                'dispatch_location_name' => 'Port of Shanghai (Yangshan)',
                'dispatch_address' => 'Yangshan Deep Water Port, Shengsi',
                'dispatch_city' => 'Shanghai',
                'dispatch_state' => 'Shanghai',
                'dispatch_pincode' => '201306',
                'dispatch_country' => 'China',
                'dispatch_lat' => 30.6218,
                'dispatch_lng' => 122.0580,
                'delivery_location_name' => $factory?->name,
                'delivery_address' => $factory?->address,
                'delivery_city' => 'Chennai',
                'delivery_state' => 'Tamil Nadu',
                'delivery_pincode' => '600058',
                'delivery_country' => 'India',
                'delivery_lat' => 13.1156,
                'delivery_lng' => 80.1551,
                'origin_port_name' => 'Port of Shanghai (Yangshan)',
                'origin_port_code' => 'CNSHA',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Chennai Port (Kamarajar Port)',
                'destination_port_code' => 'INMAA',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 3. Domestic Road: Chennai → Bengaluru
            [
                'name' => 'Domestic Road: Chennai → Bengaluru',
                'trip_type' => TripType::Domestic,
                'transport_mode' => TripTransportationMode::Road,
                'dispatch_location_name' => $factory?->name,
                'dispatch_address' => $factory?->address,
                'dispatch_city' => 'Chennai',
                'dispatch_state' => 'Tamil Nadu',
                'dispatch_pincode' => '600058',
                'dispatch_country' => 'India',
                'dispatch_lat' => 13.1156,
                'dispatch_lng' => 80.1551,
                'delivery_location_name' => $blr?->name,
                'delivery_address' => $blr?->address,
                'delivery_city' => 'Bengaluru',
                'delivery_state' => 'Karnataka',
                'delivery_pincode' => '560100',
                'delivery_country' => 'India',
                'delivery_lat' => 12.8441,
                'delivery_lng' => 77.6689,
                'origin_port_name' => null,
                'origin_port_code' => null,
                'origin_port_category' => null,
                'destination_port_name' => null,
                'destination_port_code' => null,
                'destination_port_category' => null,
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 4. Export Multimodal: Chennai Factory → Chennai Port → Colombo → Hamburg
            [
                'name' => 'Export Multimodal: Chennai → Colombo → Hamburg',
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Multimodal,
                'dispatch_location_name' => $factory?->name,
                'dispatch_address' => $factory?->address,
                'dispatch_city' => 'Chennai',
                'dispatch_state' => 'Tamil Nadu',
                'dispatch_pincode' => '600058',
                'dispatch_country' => 'India',
                'dispatch_lat' => 13.1156,
                'dispatch_lng' => 80.1551,
                'delivery_location_name' => 'Port of Hamburg',
                'delivery_address' => 'Am Kaiserkai, Hamburg',
                'delivery_city' => 'Hamburg',
                'delivery_state' => 'Hamburg',
                'delivery_pincode' => '20457',
                'delivery_country' => 'Germany',
                'delivery_lat' => 53.5439,
                'delivery_lng' => 9.9569,
                'origin_port_name' => 'Chennai Port (Kamarajar Port)',
                'origin_port_code' => 'INMAA',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Port of Hamburg',
                'destination_port_code' => 'DEHAM',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 5. Import Sea: Singapore → Chennai
            [
                'name' => 'Import Sea: Singapore → Chennai',
                'trip_type' => TripType::Import,
                'transport_mode' => TripTransportationMode::Sea,
                'dispatch_location_name' => 'Port of Singapore (PSA)',
                'dispatch_address' => 'Tanjong Pagar Terminal, Singapore',
                'dispatch_city' => 'Singapore',
                'dispatch_state' => 'Singapore',
                'dispatch_pincode' => '099253',
                'dispatch_country' => 'Singapore',
                'dispatch_lat' => 1.2566,
                'dispatch_lng' => 103.8198,
                'delivery_location_name' => 'Chennai Port (Kamarajar Port)',
                'delivery_address' => 'Rajaji Salai, George Town',
                'delivery_city' => 'Chennai',
                'delivery_state' => 'Tamil Nadu',
                'delivery_pincode' => '600001',
                'delivery_country' => 'India',
                'delivery_lat' => 13.0836,
                'delivery_lng' => 80.2969,
                'origin_port_name' => 'Port of Singapore (PSA)',
                'origin_port_code' => 'SGSIN',
                'origin_port_category' => 'port',
                'destination_port_name' => 'Chennai Port (Kamarajar Port)',
                'destination_port_code' => 'INMAA',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
            // 6. Domestic Road: Chennai → Delhi
            [
                'name' => 'Domestic Road: Chennai → Delhi',
                'trip_type' => TripType::Domestic,
                'transport_mode' => TripTransportationMode::Road,
                'dispatch_location_name' => $factory?->name,
                'dispatch_address' => $factory?->address,
                'dispatch_city' => 'Chennai',
                'dispatch_state' => 'Tamil Nadu',
                'dispatch_pincode' => '600058',
                'dispatch_country' => 'India',
                'dispatch_lat' => 13.1156,
                'dispatch_lng' => 80.1551,
                'delivery_location_name' => 'ICD Tughlakabad',
                'delivery_address' => 'ICD Tughlakabad, New Delhi',
                'delivery_city' => 'New Delhi',
                'delivery_state' => 'Delhi',
                'delivery_pincode' => '110044',
                'delivery_country' => 'India',
                'delivery_lat' => 28.5011,
                'delivery_lng' => 77.2877,
                'origin_port_name' => null,
                'origin_port_code' => null,
                'origin_port_category' => null,
                'destination_port_name' => null,
                'destination_port_code' => null,
                'destination_port_category' => null,
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
        ];
    }

    private function genericRoutes(Customer $customer, $locations, $ports, User $user): array
    {
        $loc = $locations->first();
        $port1 = $ports->where('port_category', 'port')->first();
        $icd = $ports->where('port_category', 'icd')->first();

        if (!$loc || !$port1) return [];

        return [
            [
                'name' => 'Export Multimodal: ' . ($loc->city ?? 'Origin') . ' → JNPT → Jebel Ali',
                'trip_type' => TripType::Export,
                'transport_mode' => TripTransportationMode::Multimodal,
                'dispatch_location_name' => $loc->name,
                'dispatch_address' => $loc->address,
                'dispatch_city' => $loc->city,
                'dispatch_state' => $loc->state,
                'dispatch_pincode' => $loc->pincode,
                'dispatch_country' => 'India',
                'dispatch_lat' => $loc->lat,
                'dispatch_lng' => $loc->lng,
                'delivery_location_name' => 'Jebel Ali Port',
                'delivery_city' => 'Dubai',
                'delivery_state' => 'Dubai',
                'delivery_country' => 'UAE',
                'delivery_lat' => 24.9857,
                'delivery_lng' => 55.0272,
                'origin_port_name' => $port1?->name,
                'origin_port_code' => $port1?->code,
                'origin_port_category' => $port1?->port_category->value,
                'destination_port_name' => 'Jebel Ali Port',
                'destination_port_code' => 'AEJEA',
                'destination_port_category' => 'port',
                'is_active' => true,
                'created_by_id' => $user->id,
            ],
        ];
    }
}
