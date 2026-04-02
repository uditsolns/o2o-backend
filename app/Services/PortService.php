<?php

namespace App\Services;

use App\Models\Port;
use App\Models\User;

class PortService
{
    public function store(array $data, User $createdBy): Port
    {
        return Port::create([...$data, 'created_by_id' => $createdBy->id]);
    }

    public function update(Port $port, array $data): Port
    {
        $port->update($data);
        return $port->fresh();
    }

    public function toggleActive(Port $port): Port
    {
        $port->update(['is_active' => !$port->is_active]);
        return $port->fresh();
    }
}
