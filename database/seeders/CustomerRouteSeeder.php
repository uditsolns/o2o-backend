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

            $dispatchLoc = $locations->first(fn($l) => !empty($l->sepio_shipping_address_id));
            $deliveryLoc = $locations->first(fn($l) => !empty($l->sepio_billing_address_id));
            $originPort = $ports->where('port_category', 'port')->first();
            $destPort = $ports->where('port_category', 'icd')->first();

            if (!$dispatchLoc || !$deliveryLoc) {
                $this->command->warn("  Skipping routes for customer #{$customer->id} — missing locations.");
                continue;
            }

            $definitions = [
                [
                    'name' => 'Export Sea Route',
                    'trip_type' => TripType::Export,
                    'transport_mode' => TripTransportationMode::Multimodal,
                    // Dispatch snapshot
                    'dispatch_location_name' => $dispatchLoc->name,
                    'dispatch_address' => $dispatchLoc->address,
                    'dispatch_city' => $dispatchLoc->city,
                    'dispatch_state' => $dispatchLoc->state,
                    'dispatch_pincode' => $dispatchLoc->pincode,
                    'dispatch_country' => $dispatchLoc->country ?? 'India',
                    'dispatch_lat' => $dispatchLoc->lat,
                    'dispatch_lng' => $dispatchLoc->lng,
                    // Delivery snapshot (port of loading — use origin port city)
                    'delivery_location_name' => $originPort?->name,
                    'delivery_address' => null,
                    'delivery_city' => null,
                    'delivery_state' => null,
                    'delivery_pincode' => null,
                    'delivery_country' => 'India',
                    // Port snapshots
                    'origin_port_name' => $originPort?->name,
                    'origin_port_code' => $originPort?->code,
                    'origin_port_category' => $originPort?->port_category->value,
                    'destination_port_name' => null,
                    'destination_port_code' => null,
                    'destination_port_category' => null,
                    'is_active' => true,
                    'created_by_id' => $user->id,
                ],
                [
                    'name' => 'Import Road Route',
                    'trip_type' => TripType::Import,
                    'transport_mode' => TripTransportationMode::Road,
                    // Dispatch snapshot (from port)
                    'dispatch_location_name' => $originPort?->name,
                    'dispatch_address' => null,
                    'dispatch_city' => null,
                    'dispatch_state' => null,
                    'dispatch_pincode' => null,
                    'dispatch_country' => 'India',
                    'dispatch_lat' => $originPort?->lat,
                    'dispatch_lng' => $originPort?->lng,
                    // Delivery snapshot
                    'delivery_location_name' => $deliveryLoc->name,
                    'delivery_address' => $deliveryLoc->address,
                    'delivery_city' => $deliveryLoc->city,
                    'delivery_state' => $deliveryLoc->state,
                    'delivery_pincode' => $deliveryLoc->pincode,
                    'delivery_country' => $deliveryLoc->country ?? 'India',
                    'delivery_lat' => $deliveryLoc->lat,
                    'delivery_lng' => $deliveryLoc->lng,
                    // Port snapshots
                    'origin_port_name' => $originPort?->name,
                    'origin_port_code' => $originPort?->code,
                    'origin_port_category' => $originPort?->port_category->value,
                    'destination_port_name' => $destPort?->name,
                    'destination_port_code' => $destPort?->code,
                    'destination_port_category' => $destPort?->port_category?->value,
                    'is_active' => true,
                    'created_by_id' => $user->id,
                ],
                [
                    'name' => 'Domestic Road Route',
                    'trip_type' => TripType::Domestic,
                    'transport_mode' => TripTransportationMode::Road,
                    // Dispatch snapshot
                    'dispatch_location_name' => $dispatchLoc->name,
                    'dispatch_address' => $dispatchLoc->address,
                    'dispatch_city' => $dispatchLoc->city,
                    'dispatch_state' => $dispatchLoc->state,
                    'dispatch_pincode' => $dispatchLoc->pincode,
                    'dispatch_country' => $dispatchLoc->country ?? 'India',
                    'dispatch_lat' => $dispatchLoc->lat,
                    'dispatch_lng' => $dispatchLoc->lng,
                    // Delivery snapshot
                    'delivery_location_name' => $deliveryLoc->name,
                    'delivery_address' => $deliveryLoc->address,
                    'delivery_city' => $deliveryLoc->city,
                    'delivery_state' => $deliveryLoc->state,
                    'delivery_pincode' => $deliveryLoc->pincode,
                    'delivery_country' => $deliveryLoc->country ?? 'India',
                    'delivery_lat' => $deliveryLoc->lat,
                    'delivery_lng' => $deliveryLoc->lng,
                    // No port for domestic road
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

            $customerRoutes = [];
            foreach ($definitions as $def) {
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
}
