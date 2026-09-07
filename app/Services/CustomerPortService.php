<?php

namespace App\Services;

use App\Jobs\SepioUpdateCompanyDetailsJob;
use App\Models\Customer;
use App\Models\CustomerPort;
use App\Models\Port;
use App\Models\User;

class CustomerPortService
{
    public function store(array $data, User $createdBy): CustomerPort
    {
        $customerId = $createdBy->isPlatformUser()
            ? ($data['customer_id'] ?? null)
            : $createdBy->customer_id;

        abort_if(!$customerId, 400, 'customer_id is required for platform users.');

        $port = Port::where('id', $data['port_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $customerPort = CustomerPort::create([
            'customer_id' => $customerId,
            'port_id' => $port->id,
            'port_category' => $port->port_category->value,
            'name' => $port->name,
            'code' => $port->code,
            'lat' => $data['lat'] ?? $port->lat,
            'lng' => $data['lng'] ?? $port->lng,
            'geo_fence_radius' => $data['geo_fence_radius'] ?? $port->geo_fence_radius,
            'is_active' => true,
        ]);

        $this->syncToSepio($customerPort);

        return $customerPort;
    }

    public function update(CustomerPort $customerPort, array $data): CustomerPort
    {
        $customerPort->update($data);
        $this->syncToSepio($customerPort);

        return $customerPort->fresh();
    }

    public function delete(CustomerPort $customerPort): void
    {
        $customer = $customerPort->customer;
        $customerPort->delete();
        $this->syncToSepio($customerPort, $customer);
    }

    public function toggleActive(CustomerPort $customerPort): CustomerPort
    {
        $customerPort->update(['is_active' => !$customerPort->is_active]);
        $this->syncToSepio($customerPort);

        return $customerPort->fresh();
    }

    private function syncToSepio(CustomerPort $customerPort, ?Customer $customer = null): void
    {
        $customer ??= $customerPort->customer;

        if ($customer?->sepio_company_id) {
            SepioUpdateCompanyDetailsJob::dispatch($customer);
        }
    }
}
