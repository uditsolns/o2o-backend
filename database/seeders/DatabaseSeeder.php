<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $role = Role::where('name', 'admin')->firstOrFail();

        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'role_id' => $role->id,
                'customer_id' => null,
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
            ]
        );
    }
}
