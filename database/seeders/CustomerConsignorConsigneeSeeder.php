<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Models\CustomerConsignee;
use App\Models\CustomerConsignor;
use Illuminate\Database\Seeder;

class CustomerConsignorConsigneeSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::where('onboarding_status', CustomerOnboardingStatus::Completed->value)->get();

        $total = 0;

        foreach ($customers as $customer) {
            foreach ($this->consignorDefinitions($customer) as $def) {
                CustomerConsignor::firstOrCreate(
                    ['customer_id' => $customer->id, 'name' => $def['name']],
                    [...$def, 'customer_id' => $customer->id]
                );
                $total++;
            }

            foreach ($this->consigneeDefinitions($customer) as $def) {
                CustomerConsignee::firstOrCreate(
                    ['customer_id' => $customer->id, 'name' => $def['name']],
                    [...$def, 'customer_id' => $customer->id]
                );
                $total++;
            }
        }

        $this->command->info("  CustomerConsignorConsigneeSeeder: {$total} records seeded.");
    }

    private function consignorDefinitions(Customer $customer): array
    {
        return [
            [
                'name' => $customer->company_name . ' - Dispatch Dept',
                'contact_number' => '98' . rand(10000000, 99999999),
                'contact_email' => 'dispatch@' . strtolower(str_replace(' ', '', $customer->company_name)) . '.test',
            ],
            [
                'name' => $customer->primary_contact_name ?? $customer->first_name . ' ' . $customer->last_name,
                'contact_number' => $customer->primary_contact_mobile ?? $customer->mobile,
                'contact_email' => $customer->primary_contact_email ?? $customer->email,
            ],
            [
                'name' => 'Rajiv Nair',
                'contact_number' => '97' . rand(10000000, 99999999),
                'contact_email' => 'rajiv.nair@' . strtolower(str_replace(' ', '', $customer->company_name)) . '.test',
            ],
        ];
    }

    private function consigneeDefinitions(Customer $customer): array
    {
        return [
            [
                'name' => 'Mumbai Customs Warehouse',
                'contact_number' => '9820000001',
                'contact_email' => 'customs.wh@mumbaiport.test',
            ],
            [
                'name' => 'Delhi ICD Receiver',
                'contact_number' => '9810000002',
                'contact_email' => 'receiver@delhiicd.test',
            ],
            [
                'name' => $customer->company_name . ' - Receiving Dept',
                'contact_number' => '98' . rand(10000000, 99999999),
                'contact_email' => 'receiving@' . strtolower(str_replace(' ', '', $customer->company_name)) . '.test',
            ],
        ];
    }
}
