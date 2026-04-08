<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Master seeder for dummy/test data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱  Starting DummyDataSeeder…');
        $this->command->info('');

        // Foundation — roles & permissions (idempotent)
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
        // Ports (dev-only dummy ports; prod runs `sepio:seed-ports`)
        $this->call(TestPortSeeder::class);

        // Customers + their admin users
        $this->call(CustomerSeeder::class);

        // Onboarding sub-resources
        $this->call(CustomerDocumentSignatorySeeder::class);
        $this->call(CustomerLocationSeeder::class);
        $this->call(CustomerPortSeeder::class);
        $this->call(CustomerRouteSeeder::class);

        // Finance
        $this->call(CustomerWalletSeeder::class);
        $this->call(WalletTransactionSeeder::class);

        // Orders → Seals → Trips
        $this->call(SealOrderSeeder::class);
        $this->call(SealSeeder::class);
        $this->call(TripSeeder::class);

        $this->command->info('');
        $this->command->info('✅  DummyDataSeeder complete.');
        $this->command->info('');
        $this->printCredentials();
    }

    private function printCredentials(): void
    {
        $this->command->table(
            ['Role', 'Email', 'Password', 'Onboarding Status'],
            [
                ['Platform Admin', 'admin@admin.com', 'password', 'N/A'],
                ['Customer Admin', 'user.*@sharmaexports.test', 'password', 'pending'],
                ['Customer Admin', 'user.*@mehtaintl.test', 'password', 'submitted'],
                ['Customer Admin', 'user.*@pateltraders.test', 'password', 'il_parked'],
                ['Customer Admin', 'user.*@raoglobal.test', 'password', 'il_approved'],
                ['Customer Admin', 'user.*@vermalogistics.test', 'password', 'completed ✅'],
                ['Customer Admin', 'user.*@iyerimpex.test', 'password', 'completed ✅'],
            ]
        );

        $this->command->info('  (* = auto-assigned ID — check the users table for exact email)');
        $this->command->info('');
    }
}
