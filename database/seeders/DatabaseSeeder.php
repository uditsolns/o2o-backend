<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱  Starting DatabaseSeeder…');
        $this->command->info('');

        $this->call(RolePermissionSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(TestPortSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(CustomerDocumentSignatorySeeder::class);
        $this->call(CustomerLocationSeeder::class);
        $this->call(CustomerPortSeeder::class);
        $this->call(CustomerRouteSeeder::class);
        $this->call(CustomerWalletSeeder::class);
        $this->call(WalletTransactionSeeder::class);
        $this->call(SealOrderSeeder::class);
        $this->call(SealSeeder::class);
        $this->call(TripSeeder::class);
        $this->call(TripSegmentSeeder::class);
        $this->call(TripTrackingPointSeeder::class);
        $this->call(TripContainerTrackingSeeder::class);
        $this->call(TripShipmentMilestoneSeeder::class);
        $this->call(CustomerConsignorConsigneeSeeder::class);

        $this->command->info('');
        $this->command->info('✅  DatabaseSeeder complete.');
    }
}
