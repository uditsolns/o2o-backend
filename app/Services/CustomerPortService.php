<?php

namespace App\Services;

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

        return CustomerPort::create([
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
    }

    public function update(CustomerPort $customerPort, array $data): CustomerPort
    {
        $customerPort->update($data);
        return $customerPort->fresh();
    }

    public function delete(CustomerPort $customerPort): void
    {
        $customerPort->delete();
    }

    public function toggleActive(CustomerPort $customerPort): CustomerPort
    {
        $customerPort->update(['is_active' => !$customerPort->is_active]);
        return $customerPort->fresh();
    }
}
