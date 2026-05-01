<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Models\CustomerPort;
use App\Models\Port;
use Illuminate\Database\Seeder;

class CustomerPortSeeder extends Seeder
{
    /**
     * @return array<int, CustomerPort[]>
     */
    public function run(): array
    {
        $customers = Customer::whereIn('onboarding_status', [
            CustomerOnboardingStatus::IlApproved->value,
            CustomerOnboardingStatus::Completed->value,
        ])->get();

        $result = [];
        $total = 0;

        foreach ($customers as $customer) {
            $picks = $this->picksFor($customer);
            $customerPorts = [];

            foreach ($picks as $code) {
                $port = Port::where('code', $code)->where('is_active', true)->first();
                if (!$port) continue;

                $cp = CustomerPort::firstOrCreate(
                    ['customer_id' => $customer->id, 'port_id' => $port->id],
                    [
                        'port_category' => $port->port_category->value,
                        'name' => $port->name,
                        'code' => $port->code,
                        'lat' => $port->lat,
                        'lng' => $port->lng,
                        'geo_fence_radius' => $port->geo_fence_radius,
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

    private function picksFor(Customer $customer): array
    {
        return match ($customer->email) {
            'kiran.verma@vermalogistics.test' => [
                'INNSA',   // JNPT — primary export port
                'INMUN',   // Mundra — secondary sea port
                'INTDL',   // ICD Tughlakabad — primary ICD
                'CFSGTIL', // CFS Gateway Terminals JNPT
            ],
            'meena.iyer@iyerimpex.test' => [
                'INMAA',   // Chennai Port — primary
                'INNSA',   // JNPT — secondary
                'INMAA4',  // ICD Tondiarpet Chennai
                'CFSBLAW', // CFS Balmer Lawrie Chennai
            ],
            'sunita.rao@raoglobal.test' => [
                'INNSA',   // JNPT
                'INCOK',   // Cochin Port
                'INBLR4',  // ICD Whitefield Bengaluru
                'CFSGTIL', // CFS Gateway JNPT
            ],
            default => [
                'INNSA',
                'INTDL',
                'CFSGTIL',
            ],
        };
    }
}
