<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
