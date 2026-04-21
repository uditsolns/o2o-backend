<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    private array $permissions = [
        // customers (IL only for create/approve/reject/park)
        'customer.view', 'customer.create', 'customer.update',
        'customer.approve', 'customer.reject', 'customer.park',

        // Third-party API inspectors (dev tool access)
        'sepio.inspect',
        'marinetraffic.inspect',

        // users
        'user.view', 'user.create', 'user.update', 'user.delete',

        // ports & masters (manage = create/update/toggle)
        'port.view', 'port.manage',

        // locations
        'location.view', 'location.create', 'location.update', 'location.delete',

        // routes
        'route.view', 'route.create', 'route.update', 'route.delete',

        // wallet & pricing (manage = create/update for IL)
        'wallet.view', 'wallet.manage',
        'pricing.view', 'pricing.manage',

        // seal orders
        'seal_order.view', 'seal_order.create',
        'seal_order.approve', 'seal_order.reject', 'seal_order.park',

        // seals
        'seal.view', 'seal.assign',

        // trips
        'trip.view', 'trip.create', 'trip.update', 'trip.complete',
        'trip.destination_confirm',

        // documents (trip + customer)
        'document.upload', 'document.delete',

        // reports
        'report.view',
    ];

    private array $customerAdminPermissions = [
        'user.view', 'user.create', 'user.update', 'user.delete',
        'port.view',
        'location.view', 'location.create', 'location.update', 'location.delete',
        'route.view', 'route.create', 'route.update', 'route.delete',
        'wallet.view',
        'pricing.view',
        'seal_order.view', 'seal_order.create',
        'seal.view', 'seal.assign',
        'trip.view', 'trip.create', 'trip.update', 'trip.complete',
        'trip.destination_confirm',
        'document.upload', 'document.delete',
        'report.view',
    ];

    public function run(): void
    {
        // Upsert all permissions
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $map = Permission::whereIn('name', $this->permissions)->pluck('id', 'name');

        // admin — all permissions
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'ILGIC Super Admin']);
        $admin->permissions()->sync($map->values()->all());

        // customer_admin — own-tenant operations
        $ca = Role::firstOrCreate(['name' => 'customer_admin'], ['description' => 'Customer Company Admin']);
        $ca->permissions()->sync(
            $map->only($this->customerAdminPermissions)->values()->all()
        );
    }
}
