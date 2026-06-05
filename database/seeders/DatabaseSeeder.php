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

        // ── Foundation: roles, permissions, platform admin user ─────────────
        $this->call(RolePermissionSeeder::class);
        $this->call(UserSeeder::class);

        // ── Master data (ports are referenced by every customer trip) ───────
        $this->call(TestPortSeeder::class);

        // ── Tenants (customers, locations, ports, routes) ────────────────────
        $this->call(CustomerSeeder::class);

        // Onboarding history must be created BEFORE documents so the
        // 'submitted' history row can refer to the right user; documents
        // and signatories run next.
        $this->call(CustomerDocumentSignatorySeeder::class);
        $this->call(CustomerLocationSeeder::class);
        $this->call(CustomerPortSeeder::class);
        $this->call(CustomerRouteSeeder::class);
        $this->call(CustomerConsignorConsigneeSeeder::class);

        // ── Customer lifecycle history (after customer is created) ──────────
        $this->call(CustomerOnboardingHistorySeeder::class);
        $this->call(CustomerSepioHistorySeeder::class);

        // ── Wallet + pricing + transactions ─────────────────────────────────
        $this->call(CustomerWalletSeeder::class);
        $this->call(TripPricingRuleSeeder::class);
        $this->call(WalletTransactionSeeder::class);

        // ── Seal orders, seals, seal history ────────────────────────────────
        $this->call(SealOrderSeeder::class);
        $this->call(SealOrderHistorySeeder::class);
        $this->call(SealSeeder::class);

        // ── Trips + tracking + milestones + events + documents ──────────────
        $this->call(TripSeeder::class);
        $this->call(TripSegmentSeeder::class);
        $this->call(TripTrackingPointSeeder::class);
        $this->call(TripContainerTrackingSeeder::class);
        $this->call(TripShipmentMilestoneSeeder::class);

        $this->command->info('');
        $this->command->info('✅  DatabaseSeeder complete.');
    }
}
