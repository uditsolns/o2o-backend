<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Models\CustomerPort;
use App\Models\Port;
use Illuminate\Database\Seeder;

/**
 * Seeds CustomerPort records by associating completed/approved customers
 * with a subset of ports from the ports master table.
 *
 * Returns a map of customer_id → [CustomerPort] for downstream seeders.
 */
class CustomerPortSeeder extends Seeder
{
    /**
     * @return array<int, CustomerPort[]>  keyed by customer_id
     */
    public function run(): array
    {
        $allPorts = Port::where('is_active', true)->get()->groupBy(fn($p) => $p->port_category->value);

        $customers = Customer::whereIn('onboarding_status', [
            CustomerOnboardingStatus::IlApproved->value,
            CustomerOnboardingStatus::Completed->value,
        ])->get();

        $result = [];
        $total = 0;

        foreach ($customers as $customer) {
            $customerPorts = [];

            // Give each customer 2 sea ports, 1 ICD, 1 CFS
            $picks = array_merge(
                $this->pickRandom($allPorts->get('port', collect()), 2),
                $this->pickRandom($allPorts->get('icd', collect()), 1),
                $this->pickRandom($allPorts->get('cfs', collect()), 1),
            );

            foreach ($picks as $port) {
                $cp = CustomerPort::firstOrCreate(
                    ['customer_id' => $customer->id, 'port_id' => $port->id],
                    [
                        'port_category' => $port->port_category->value,
                        'name' => $port->name,
                        'code' => $port->code,
                        'lat' => $port->lat,
                        'lng' => $port->lng,
                        'geo_fence_radius' => $port->geo_fence_radius ?? 2000,
                        'is_active' => true,
                    ]
                );
                $customerPorts[] = $cp;
                $total++;
            }

            $result[$customer->id] = $customerPorts;
        }

        $this->command->info("  CustomerPortSeeder: {$total} customer-port associations seeded.");

        return $result;
    }

    private function pickRandom(\Illuminate\Support\Collection $collection, int $n): array
    {
        if ($collection->isEmpty()) return [];
        return $collection->shuffle()->take($n)->values()->all();
    }
}
