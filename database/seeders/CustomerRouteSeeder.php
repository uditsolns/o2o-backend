<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\CustomerRoute;
use App\Models\Port;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds 3 route templates per completed/approved customer:
 *   - Export by sea
 *   - Import by road
 *   - Domestic multimodal
 */
class CustomerRouteSeeder extends Seeder
{
    /**
     * @return array<int, CustomerRoute[]>  keyed by customer_id
     */
    public function run(): array
    {
        $customers = Customer::whereIn('onboarding_status', [
            CustomerOnboardingStatus::IlApproved->value,
            CustomerOnboardingStatus::Completed->value,
        ])->get();

        $seaPorts = Port::where('port_category', 'port')->where('is_active', true)->get();
        $icdPorts = Port::where('port_category', 'icd')->where('is_active', true)->get();

        $result = [];
        $total = 0;

        foreach ($customers as $customer) {
            $user = User::where('customer_id', $customer->id)->firstOrFail();
            $locations = CustomerLocation::where('customer_id', $customer->id)->get();

            $dispatchLoc = $locations->first(fn($l) => !empty($l->sepio_shipping_address_id));
            $deliveryLoc = $locations->first(fn($l) => !empty($l->sepio_billing_address_id));
            $originPort = $seaPorts->shuffle()->first();
            $destPort = $icdPorts->shuffle()->first();

            if (!$dispatchLoc || !$deliveryLoc || !$originPort) {
                continue; // skip if locations not yet seeded
            }

            $routes = [
                [
                    'name' => 'Export Sea Route',
                    'trip_type' => TripType::Export,
                    'transport_mode' => TripTransportationMode::Multimodal,
                    'dispatch_location_id' => $dispatchLoc->id,
                    'delivery_location_id' => null,
                    'origin_port_id' => $originPort->id,
                    'destination_port_id' => null,
                    'notes' => 'Standard export route via JNPT.',
                    'is_active' => true,
                ],
                [
                    'name' => 'Import Road Route',
                    'trip_type' => TripType::Import,
                    'transport_mode' => TripTransportationMode::Road,
                    'dispatch_location_id' => null,
                    'delivery_location_id' => $deliveryLoc->id,
                    'origin_port_id' => $originPort->id,
                    'destination_port_id' => $destPort?->id,
                    'notes' => 'Road transport from port to warehouse.',
                    'is_active' => true,
                ],
                [
                    'name' => 'Domestic Multimodal Route',
                    'trip_type' => TripType::Domestic,
                    'transport_mode' => TripTransportationMode::Multimodal,
                    'dispatch_location_id' => $dispatchLoc->id,
                    'delivery_location_id' => $deliveryLoc->id,
                    'origin_port_id' => null,
                    'destination_port_id' => null,
                    'notes' => 'Domestic intercity shipment.',
                    'is_active' => true,
                ],
            ];

            $customerRoutes = [];
            foreach ($routes as $def) {
                $route = CustomerRoute::firstOrCreate(
                    ['customer_id' => $customer->id, 'name' => $def['name']],
                    [...$def, 'customer_id' => $customer->id, 'created_by_id' => $user->id]
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
